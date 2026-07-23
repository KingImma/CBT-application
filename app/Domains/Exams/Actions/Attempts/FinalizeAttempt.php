<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Attempts;

use App\Domains\Exams\Events\ExamAttemptsUpdated;
use App\Domains\Exams\Events\ExamSessionStateUpdated;
use App\Domains\Exams\State\ExamAttemptStateMachine;
use App\Domains\Exams\Support\ExamSessionStateStore;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class FinalizeAttempt
{
    public function __construct(
        private ExamSessionStateStore $stateStore,
        private GradeExamAttempt $gradeAttempt,
        private ExamAttemptStateMachine $stateMachine,
    ) {}

    public function execute(ExamAttempt $attempt, ?User $actor = null, string $reason = 'submit'): ExamAttempt
    {
        return $reason === 'stale_heartbeat'
            ? $this->timeout($attempt)
            : $this->submit($attempt, $actor);
    }

    private function submit(ExamAttempt $attempt, ?User $actor): ExamAttempt
    {
        $attempt->update([
            'status' => $this->stateMachine->submit($attempt, $actor)->value,
            'submitted_at' => now(),
        ]);

        $attempt->update([
            'status' => $this->stateMachine->claimForGrading($attempt->fresh())->value,
        ]);

        try {
            $this->gradeAttempt->execute($attempt->fresh());
        } catch (\Throwable $e) {
            $attempt->fresh()->update([
                'status' => $this->stateMachine->fail($attempt->fresh())->value,
            ]);

            Log::error('Exam grading failed', [
                'attempt_id' => $attempt->id,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } finally {
            $this->destroySessionState($attempt);
        }

        return $attempt->fresh();
    }

    private function timeout(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt->update([
                'status' => $this->stateMachine->timeout($attempt)->value,
                'submitted_at' => now(),
                'time_spent_seconds' => (int) now()->diffInSeconds($attempt->started_at),
            ]);

            $fresh = $attempt->fresh();

            $exam = $fresh->relationLoaded('exam') ? $fresh->getRelation('exam') : $fresh->exam()->first();
            if ($exam === null) {
                $exam = Exam::find($fresh->exam_id);
            }

            if ($exam !== null) {
                $exam->increment('completed_attempts');
                $exam->refresh();

                $shouldComplete = $exam->completed_attempts >= $exam->expected_attempts
                    || ($exam->window_end !== null && now()->gte($exam->window_end));

                if ($shouldComplete) {
                    $exam->update(['status' => ExamStatus::Completed]);
                    $exam->refresh();
                }

                event(new ExamAttemptsUpdated(
                    examId: $fresh->exam_id,
                    completedAttempts: $exam->completed_attempts,
                    expectedAttempts: $exam->expected_attempts,
                    status: $shouldComplete ? ExamStatus::Completed : ExamStatus::Active,
                    tenantId: (string) tenant('id'),
                ));
            }

            $this->destroySessionState($fresh);

            return $fresh;
        });
    }

    private function destroySessionState(ExamAttempt $attempt): void
    {
        $tenantId = (string) tenant('id');

        $this->stateStore->destroy($tenantId, $attempt->id);

        event(new ExamSessionStateUpdated(
            attemptId: $attempt->id,
            tenantId: $tenantId,
            timeRemainingSeconds: 0,
            connectionAlive: false,
        ));
    }
}
