<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Attempts;

use App\Actions\Base\UpdateAction;
use App\Actions\Tenants\Exam\ExamAttemptGuards;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Events\ExamAttemptsUpdated;
use App\Events\ExamSessionStateUpdated;
use App\Jobs\GradeExamAttemptJob;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use App\Support\Exam\ExamSessionStateStore;

final class FinalizeAttempt
{
    public function __construct(
        private UpdateAction          $action,
        private ExamSessionStateStore $stateStore,
    ) {}

    public function execute(ExamAttempt $attempt, ?User $actor = null, string $reason = 'submit'): ExamAttempt
    {
        return $reason === 'stale_heartbeat'
            ? $this->timeout($attempt)
            : $this->submit($attempt, $actor);
    }

    private function submit(ExamAttempt $attempt, ?User $actor): ExamAttempt
    {
        $updated = $this->action->execute(
            $attempt,
            ['actor' => $actor],
            guard: ExamAttemptGuards::canSubmit($actor),
            prepare: fn (ExamAttempt $a, array $d) => [
                'status'       => ExamAttemptStatus::Submitted->value,
                'submitted_at' => now(),
            ],
            after: function (ExamAttempt $a, array $d) {
                // Dispatch async grading — attempt status moves Submitted→Grading→Graded in job
                GradeExamAttemptJob::dispatch($a->id, (string) tenant('id'));
                $this->destroySessionState($a);
            },
        );

        return $updated;
    }

    private function timeout(ExamAttempt $attempt): ExamAttempt
    {
        return $this->action->execute(
            $attempt,
            [],
            // Guard: must still be InProgress (scheduler may double-fire)
            guard: ExamAttemptGuards::isInProgress(),
            prepare: fn (ExamAttempt $a, array $d) => [
                'status'           => ExamAttemptStatus::Timed_out->value,
                'submitted_at'     => now(),
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
