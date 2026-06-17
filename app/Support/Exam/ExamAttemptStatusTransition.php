<?php

declare(strict_types=1);

namespace App\Support\Exam;

use App\Enums\ExamAttemptStatus;
use App\Exceptions\Domain\Exam\ExamAttemptStatusTransitionException;

final class ExamAttemptStatusTransition
{
    private const array LEGAL_TRANSITIONS = [
        ExamAttemptStatus::InProgress->value => [
            ExamAttemptStatus::Submitted->value,
            ExamAttemptStatus::Timed_out->value,
        ],
        ExamAttemptStatus::Submitted->value => [
            ExamAttemptStatus::Grading->value,
        ],
        ExamAttemptStatus::Timed_out->value => [
            ExamAttemptStatus::Submitted->value,
        ],
        ExamAttemptStatus::Grading->value => [
            ExamAttemptStatus::Graded->value,
            ExamAttemptStatus::Failed->value,
        ],
        ExamAttemptStatus::Failed->value => [
            ExamAttemptStatus::Grading->value,
        ],
    ];

    public static function isAllowed(string $from, string $to): bool
    {
        return isset(self::LEGAL_TRANSITIONS[$from])
            && in_array($to, self::LEGAL_TRANSITIONS[$from], true);
    }

    public static function assertAllowed(string $from, string $to): void
    {
        if (! self::isAllowed($from, $to)) {
            throw new ExamAttemptStatusTransitionException(
                "Illegal status transition from {$from} to {$to}"
            );
        }
    }
}
