<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Support;

use App\Domains\Assessments\Exceptions\AssessmentCannotBeActivatedException;
use App\Domains\Assessments\Exceptions\AssessmentCannotBeCompletedException;
use App\Domains\Assessments\Exceptions\AssessmentCannotBeOpenedException;
use App\Domains\Assessments\Exceptions\AssessmentStateTransitionException;
use App\Models\Tenant\Assessment;
use Closure;

final class AssessmentLifecycleRules
{
    public static function canOpen(): Closure
    {
        return function (Assessment $assessment): void {
            throw_unless(
                $assessment->isDraft(),
                new AssessmentCannotBeOpenedException(
                    'Only draft assessments can be opened.'
                )
            );

            throw_unless(
                $assessment->submission_closes_at !== null
                    && $assessment->submission_closes_at->isFuture(),
                new AssessmentCannotBeOpenedException(
                    'A submission close time in the future must be set before opening.'
                )
            );
        };
    }

    public static function canCloseSubmissions(): Closure
    {
        return function (Assessment $assessment): void {
            throw_unless(
                $assessment->isOpen(),
                new AssessmentStateTransitionException(
                    'Only open assessments can have submissions closed.'
                )
            );
        };
    }

    public static function canReopen(): Closure
    {
        return function (Assessment $assessment): void {
            throw_unless(
                $assessment->isSubmissionsClosed(),
                new AssessmentCannotBeOpenedException(
                    'Only assessments with closed submissions can be reopened.'
                )
            );

            throw_unless(
                $assessment->submission_closes_at !== null
                    && $assessment->submission_closes_at->isFuture(),
                new AssessmentCannotBeOpenedException(
                    'A new submission close time in the future must be set before reopening.'
                )
            );
        };
    }

    public static function canActivate(): Closure
    {
        return function (Assessment $assessment): void {
            throw_unless(
                $assessment->isSubmissionsClosed(),
                new AssessmentCannotBeActivatedException(
                    'Only assessments with closed submissions can be activated.'
                )
            );

            throw_unless(
                $assessment->student_starts_at !== null
                    && $assessment->student_ends_at !== null,
                new AssessmentCannotBeActivatedException(
                    'Both student start and end times must be configured before activation.'
                )
            );

            throw_unless(
                $assessment->student_starts_at < $assessment->student_ends_at,
                new AssessmentCannotBeActivatedException(
                    'The student start time must be before the student end time.'
                )
            );

            throw_unless(
                $assessment->approvedSubmissionsCount() > 0,
                new AssessmentCannotBeActivatedException(
                    'At least one approved submission is required before activation.'
                )
            );
        };
    }

    public static function canComplete(): Closure
    {
        return function (Assessment $assessment): void {
            throw_unless(
                $assessment->isActive(),
                new AssessmentCannotBeCompletedException(
                    'Only active assessments can be completed.'
                )
            );
        };
    }
}
