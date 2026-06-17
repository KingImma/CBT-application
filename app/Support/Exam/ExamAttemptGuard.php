<?php

declare(strict_types=1);

namespace App\Support\Exam;

use App\Enums\ExamAttemptStatus;
use App\Exceptions\Domain\Exam\ExamAttemptStatusTransitionException;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;

final class ExamAttemptGuard
{
    public static function canTransitionTo(
        ExamAttempt $attempt,
        ExamAttemptStatus $targetStatus,
        ?User $actor = null
    ): bool {
        if (! ExamAttemptStatusTransition::isAllowed($attempt->status, $targetStatus->value)) {
            return false;
        }

        if ($actor !== null && $targetStatus === ExamAttemptStatus::Submitted) {
            return $actor->id === $attempt->student_id;
        }

        return true;
    }

    public static function assertCanTransitionTo(
        ExamAttempt $attempt,
        ExamAttemptStatus $targetStatus,
        ?User $actor = null
    ): void {
        if (! self::canTransitionTo($attempt, $targetStatus, $actor)) {
            throw new ExamAttemptStatusTransitionException(
                "Transition to {$targetStatus->value} not allowed for attempt {$attempt->id}"
            );
        }
    }
}
