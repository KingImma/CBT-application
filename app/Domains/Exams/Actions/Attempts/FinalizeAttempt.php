<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Attempts;

use App\Domains\Exams\Events\ExamAttemptsUpdated;
use App\Domains\Exams\Events\ExamSessionStateUpdated;
use App\Domains\Exams\Jobs\GradeExamAttemptJob;
use App\Domains\Exams\State\ExamAttemptStateMachine;
use App\Domains\Exams\Support\ExamSessionStateStore;
use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

final class FinalizeAttempt
{
    public function __construct(
        private ExamSessionStateStore $stateStore,
        private ExamAttemptStateMachine $stateMachine,
    ) {
    }

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
            
        $this->destroySessionState($attempt);

        GradeExamAttemptJob::dispatch(
            (string) $attempt->id,
            (string) tenant('id')
        );
        
        return $attempt->fresh();
    }

    private function timeout(ExamAttempt $attempt): ExamAttempt
    {
        $timedOut =  DB::transaction(function () use ($attempt) {
            $attempt->update([
                'status' => $this->stateMachine->timeout($attempt)->value,
                'submitted_at' => now(),
                'time_spent_seconds' => (int) now()->diffInSeconds($attempt->started_at, true),
            ]);

            $fresh = $attempt->fresh();

            $exam = $fresh->relationLoaded('exam') ? $fresh->getRelation('exam') : $fresh->exam()->first();
            if ($exam === null) {
                $exam = Exam::find($fresh->exam_id);
            }

            if ($exam !== null) {
                $exam->increment('completed_attempts');
                $exam->ensureExpectedAttempts();

                $shouldComplete = $exam->shouldAutoComplete();

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

        GradeExamAttemptJob::dispatch((string) $timedOut->id, (string) tenant('id'));

        return $timedOut;
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
