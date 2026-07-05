<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Exceptions\Domain\ExamAttempt\AttemptCannotBeSubmittedException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Closure;
use RuntimeException;

final class ExamAttemptGuards
{
    /** Exam must be active and must be past the scheduled start time, window still open */
    public static function canStart(User $student): Closure
    {
        return function (Exam $exam) use ($student) {
            throw_unless($exam->status === ExamStatus::Active, new RuntimeException('Exam is not active'));
            throw_unless($exam->scheduled_start !== null, new RuntimeException('Exam has no scheduled start time'));
            throw_if(now()->lt($exam->scheduled_start), new RuntimeException(
                'Exam has not started yet. Available at '.$exam->scheduled_start->toIso8601String()
            ));
            throw_if(
                $exam->window_end !== null && now()->gte($exam->window_end),
                new RuntimeException('The exam window has closed.')
            );
            throw_if(
                $exam->attempts()->forStudent($student->id)->inProgress()->exists(),
                new RuntimeException('You already have an active exam attempt.')
            );

            $last = $exam->attempts()->forStudent($student->id)->max('attempt_number');
            throw_if(
                $last !== null && $last >= ($exam->max_attempts ?? 1),
                new RuntimeException('Maximum attempts exceeded')
            );
            throw_unless($student->is_active, new RuntimeException('Student account is not active.'));
        };
    }

    /** Attempt must be in progress and not expired */
    public static function canSubmit(?User $student): Closure
    {
        return function (ExamAttempt $attempt) use ($student) {
            throw_unless(
                +self::statusMatches($attempt->status, ExamAttemptStatus::InProgress),
                new RuntimeException('Only in-progress attempts can be submitted.')
            );

            if ($student === null) {
                throw new AttemptCannotBeSubmittedException('Exam time has expired.');
            }

            throw_unless(
                $student->id === $attempt->student_id,
                new RuntimeException('Unauthorized.')
            );
            throw_if(
                $attempt->getTimeRemainingSeconds() <= 0,
                new AttemptCannotBeSubmittedException('Exam time has expired.')
            );
        };
    }

    /** Attempt is InProgress — used for answer-save gate */
    public static function isInProgress(): Closure
    {
        return fn (ExamAttempt $attempt) => throw_unless(
            self::statusMatches($attempt->status, ExamAttemptStatus::InProgress),
            new RuntimeException('Attempt is no longer active.')
        );
    }

    /** Attempt must be Submitted before grading can begin */
    public static function canGrade(): Closure
    {
        return fn (ExamAttempt $attempt) => throw_unless(
            self::statusMatches($attempt->status, ExamAttemptStatus::Submitted),
            new RuntimeException('Only submitted attempts can be graded.')
        );
    }

    public static function statusMatches(ExamAttemptStatus|string|null $actual, ExamAttemptStatus $expected): bool
    {
        return ($actual instanceof ExamAttemptStatus
            ? $actual
            : ExamAttemptStatus::tryFrom((string) $actual)
        ) === $expected;
    }
}
