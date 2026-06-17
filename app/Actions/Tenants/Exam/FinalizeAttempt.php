<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Events\ExamAttemptsUpdated;
use App\Events\ExamSessionStateUpdated;
use App\Jobs\GradeExamAttempt;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use App\Support\Exam\ExamAttemptGuard;
use App\Support\Exam\ExamSessionStateStore;
use Illuminate\Support\Facades\DB;

final class FinalizeAttempt
{
    public function __construct(
        private ExamSessionStateStore $stateStore,
    ) {}

    public function execute(ExamAttempt $attempt, ?User $actor = null, ?string $reason = null): ExamAttempt
    {
        if ($reason === 'stale_heartbeat') {
            ExamAttemptGuard::assertCanTransitionTo($attempt, ExamAttemptStatus::Timed_out);

            return DB::transaction(function () use ($attempt) {
                $attempt->status = ExamAttemptStatus::Timed_out->value;
                $attempt->submitted_at = now();
                $attempt->time_spent_seconds = (int) now()->diffInSeconds($attempt->started_at);
                $attempt->save();

                event(new ExamAttemptsUpdated(
                    examId: $attempt->exam_id,
                    completedAttempts: 0,
                    expectedAttempts: 0,
                    status: ExamStatus::Active,
                    tenantId: (string) tenant('id'),
                ));

                $this->clearSessionState($attempt);

                return $attempt->fresh();
            });
        }

        ExamAttemptGuard::assertCanTransitionTo($attempt, ExamAttemptStatus::Submitted, $actor);

        $attempt = DB::transaction(function () use ($attempt) {
            $attempt->status = ExamAttemptStatus::Submitted->value;
            $attempt->submitted_at = now();
            $attempt->save();

            return $attempt->fresh();
        });

        GradeExamAttempt::dispatch($attempt->id, (string) tenant('id'));

        $this->notifySessionSubmitted($attempt);

        return $attempt;
    }

    private function clearSessionState(ExamAttempt $attempt): void
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

    private function notifySessionSubmitted(ExamAttempt $attempt): void
    {
        $tenantId = (string) tenant('id');

        event(new ExamSessionStateUpdated(
            attemptId: $attempt->id,
            tenantId: $tenantId,
            timeRemainingSeconds: $attempt->getTimeRemainingSeconds(),
            lastActivityAt: now()->toIso8601String(),
            connectionAlive: false,
        ));

        $this->stateStore->destroy($tenantId, $attempt->id);
    }
}
