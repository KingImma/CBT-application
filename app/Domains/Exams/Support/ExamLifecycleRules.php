<?php

declare(strict_types=1);

namespace App\Domains\Exams\Support;

use App\Domains\Exams\Exceptions\ExamCannotBeActivatedException;
use App\Domains\Exams\Exceptions\ExamCannotBeCompletedException;
use App\Domains\Exams\Exceptions\ExamCannotBeDeletedException;
use App\Domains\Exams\Exceptions\ExamCannotBeSubmittedException;
use App\Domains\Exams\Exceptions\ExamStateTransitionException;
use App\Models\Tenant\Exam;
use Closure;

final class ExamLifecycleRules
{
    public static function canSubmitForReview(): Closure
    {
        return function (Exam $exam): void {
            throw_unless(
                $exam->canSubmitForReview(),
                new ExamCannotBeSubmittedException(
                    'Exam must have at least one question and a total mark greater than zero before it can be submitted for review.'
                )
            );
        };
    }

    public static function canActivate(): Closure
    {
        return function (Exam $exam): void {
            throw_unless(
                $exam->isSubmitted(),
                new ExamCannotBeActivatedException(
                    'Only submitted exams can be activated.'
                )
            );

            throw_unless(
                $exam->duration_minutes > 0,
                new ExamCannotBeActivatedException(
                    'Exam duration must be greater than zero.'
                )
            );

            throw_unless(
                $exam->pass_mark !== null,
                new ExamCannotBeActivatedException(
                    'A pass mark must be configured before the exam can be activated.'
                )
            );

            throw_unless(
                $exam->pass_mark <= $exam->total_marks,
                new ExamCannotBeActivatedException(
                    'The pass mark cannot exceed the total marks.'
                )
            );

            throw_unless(
                $exam->scheduled_start !== null,
                new ExamCannotBeActivatedException(
                    'A scheduled start date and time must be configured before the exam can be activated.'
                )
            );
        };
    }

    public static function canComplete(): Closure
    {
        return function (Exam $exam): void {
            throw_unless(
                $exam->canComplete(),
                new ExamCannotBeCompletedException(
                    'Only active exams can be completed.'
                )
            );
        };
    }

    public static function isCompleted(): Closure
    {
        return function (Exam $exam): void {
            throw_unless(
                $exam->isCompleted(),
                new ExamStateTransitionException(
                    'Results can only be published for completed exams.'
                )
            );
        };
    }

    public static function isPublished(): Closure
    {
        return function (Exam $exam): void {
            throw_unless(
                $exam->isPublished(),
                new ExamStateTransitionException(
                    'Cannot republish or unpublish results for an exam that is not published.'
                )
            );
        };
    }

    public static function isDraft(): Closure
    {
        return function (Exam $exam): void {
            throw_unless(
                $exam->isDraft(),
                new ExamStateTransitionException(
                    'Only draft exams can be updated.'
                )
            );
        };
    }

    public static function canDelete(): Closure
    {
        return function (Exam $exam): void {
            throw_if(
                $exam->isActive(),
                new ExamCannotBeDeletedException(
                    'Cannot delete an active exam.'
                )
            );

            throw_if(
                $exam->isPublished(),
                new ExamCannotBeDeletedException(
                    'Cannot delete a published exam.'
                )
            );

            throw_if(
                $exam->completed_attempts > 0,
                new ExamCannotBeDeletedException(
                    "Cannot delete an exam with {$exam->completed_attempts} completed attempt(s). Results would be permanently lost."
                )
            );
        };
    }
}
