<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Attempts;

use App\Actions\Base\UpdateAction;
use App\Actions\Tenants\Exam\ExamAttemptGuards;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Events\ExamAttemptsUpdated;
use App\Events\ExamSessionStateUpdated;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use App\Support\Exam\ExamSessionStateStore;
use Illuminate\Support\Facades\Log;

final class FinalizeAttempt
{
    public function __construct(
        private UpdateAction $action,
        private ExamSessionStateStore $stateStore,
        private GradeExamAttempt $gradeAttempt,
    ) {}

    public function execute(ExamAttempt $attempt, ?User $actor = null, string $reason = 'submit'): ExamAttempt
    {
        return $reason === 'stale_heartbeat'
            ? $this->timeout($attempt)
            : $this->submit($attempt, $actor);
    }

    private function submit(ExamAttempt $attempt, ?User $actor): ExamAttempt
    {
        ExamAttemptGuards::canSubmit($actor)($attempt, ['actor' => $actor]);

        $attempt->update([
            'status' => ExamAttemptStatus::Submitted->value,
            'submitted_at' => now(),
        ]);

        $attempt->update([
            'status' => ExamAttemptStatus::Grading->value,
        ]);

        try {
            $this->gradeAttempt->execute($attempt->fresh());
        } catch (\Throwable $e) {
            // Runs AFTER GradeExamAttempt's internal transaction has already
            // rolled back and released control here — this is its own commit.
            $attempt->fresh()->update(['status' => ExamAttemptStatus::Failed->value]);

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
        return $this->action->execute(
            $attempt,
            [],
            guard: ExamAttemptGuards::isInProgress(),
            prepare: fn (ExamAttempt $a, array $d) => [
                'status' => ExamAttemptStatus::Timed_out->value,
                'submitted_at' => now(),
                'time_spent_seconds' => (int) now()->diffInSeconds($a->started_at),
            ],
            after: function (ExamAttempt $a, array $d) {
                $exam = $a->relationLoaded('exam') ? $a->getRelation('exam') : $a->exam()->first();

                if ($exam === null) {
                    $exam = Exam::find($a->exam_id);
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
                        examId: $a->exam_id,
                        completedAttempts: $exam->completed_attempts,
                        expectedAttempts: $exam->expected_attempts,
                        status: $shouldComplete ? ExamStatus::Completed : ExamStatus::Active,
                        tenantId: (string) tenant('id'),
                    ));
                }

                $this->destroySessionState($a);
            },
        );
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
