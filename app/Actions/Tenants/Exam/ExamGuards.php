<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Exceptions\Domain\Exam\ExamCannotBeActivatedException;
use App\Exceptions\Domain\Exam\ExamCannotBeCompletedException;
use App\Exceptions\Domain\Exam\ExamCannotBeDeletedException;
use App\Exceptions\Domain\Exam\ExamCannotBeSubmittedException;
use App\Exceptions\Domain\Exam\ExamStateTransitionException;
use App\Models\Tenant\Exam;
use Closure;

final class ExamGuards
{
    public static function canSubmitForReview(): Closure
    {
        return function (Exam $e) {
            throw_unless($e->canSubmitForReview(), ExamCannotBeSubmittedException::class);
        };
    }

    public static function canActivate(): Closure
    {
        return function (Exam $e) {
            throw_unless($e->canActivate(), ExamCannotBeActivatedException::class);
        };
    }

    public static function canComplete(): Closure
    {
        return function (Exam $e) {
            throw_unless($e->canComplete(), ExamCannotBeCompletedException::class);
        };
    }

    public static function isCompleted(): Closure
    {
        return function (Exam $e) {
            throw_unless(
                $e->isCompleted(),
                new ExamStateTransitionException('Results can only be published for completed exams')
            );
        };
    }

    public static function isPublished(): Closure
    {
        return function (Exam $e) {
            throw_unless(
                $e->isPublished(),
                new ExamStateTransitionException('Cannot republish or unpublish results for an exam that is not published')
            );
        };
    }

    public static function isDraft(): Closure
    {
       return fn (Exam $e) => throw_unless($e->isDraft(), new ExamStateTransitionException('Only draft exams can be updated'));
    }

    public static function canDelete(): Closure
    {
        return function (Exam $e) {
            throw_if($e->isActive(), new ExamCannotBeDeletedException('Cannot delete an active exam'));
            throw_if($e->isPublished(), new ExamCannotBeDeletedException('Cannot delete a published exam'));
            throw_if(
                $e->completed_attempts > 0,
                new ExamCannotBeDeletedException(
                    "Cannot delete an exam with {$e->completed_attempts} completed attempt(s).\n" .
                    "Results would be permanently lost"
                )
            );
        };
    }
}
