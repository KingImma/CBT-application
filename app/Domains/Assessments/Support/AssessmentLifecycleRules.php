<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Support;

use App\Domains\Assessments\Exceptions\AssessmentCannotBeActivatedException;
use App\Domains\Assessments\Exceptions\AssessmentCannotBeCompletedException;
use App\Domains\Assessments\Exceptions\AssessmentCannotBePublishedException;
use App\Domains\Assessments\Exceptions\AssessmentStateTransitionException;
use App\Enums\SubmissionStatus;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\Submission;
use Closure;

final class AssessmentLifecycleRules
{
    public static function canCloseSubmissions(): Closure
    {
        return function (AssessmentSchedule $schedule): void {
            throw_unless(
                $schedule->isQuestionSubmissionOpen(),
                new AssessmentStateTransitionException(
                    'Only open question windows can be closed.'
                )
            );
        };
    }

    public static function canReopen(): Closure
    {
        return function (AssessmentSchedule $schedule): void {
            throw_unless(
                $schedule->isQuestionSubmissionClosed(),
                new AssessmentStateTransitionException(
                    'Only closed question windows can be reopened.'
                )
            );

            throw_unless(
                $schedule->isDraft(),
                new AssessmentStateTransitionException(
                    'A schedule that has been activated cannot reopen submissions.'
                )
            );
        };
    }

    public static function canActivate(): Closure
    {
        return function (AssessmentSchedule $schedule): void {
            throw_unless(
                $schedule->isQuestionSubmissionClosed(),
                new AssessmentCannotBeActivatedException(
                    'The question submission window must be closed before activation.'
                )
            );

            throw_unless(
                $schedule->masterWindowIsSet(),
                new AssessmentCannotBeActivatedException(
                    'Both student start and end times must be configured before activation.'
                )
            );

            throw_unless(
                $schedule->approvedSubmissionsCount() > 0,
                new AssessmentCannotBeActivatedException(
                    'At least one approved submission is required before activation.'
                )
            );

            // Every approved paper must have a subject slot inside the master
            // window, otherwise materialisation would produce an unscheduled exam.
            $unscheduled = $schedule->submissions()
                ->where('status', SubmissionStatus::Approved->value)
                ->whereDoesntHave('subject.scheduleSubjects', fn ($q) => $q->where('assessment_schedule_id', $schedule->id))
                ->count();

            throw_if(
                $unscheduled > 0,
                new AssessmentCannotBeActivatedException(
                    "{$unscheduled} approved submission(s) have no subject slot on this schedule."
                )
            );
        };
    }

    public static function canComplete(): Closure
    {
        return function (AssessmentSchedule $schedule): void {
            throw_unless(
                $schedule->isActive(),
                new AssessmentCannotBeCompletedException(
                    'Only active schedules can be completed.'
                )
            );
        };
    }

    public static function canPublish(): Closure
    {
        return function (AssessmentSchedule $schedule): void {
            // The teacher submission is the source of truth; the internal exam
            // only drives the student flow. Completing an exam chains a
            // `completed` submission status via the ExamCompleted event.
            $notReady = $schedule->submissions()
                ->whereNotNull('exam_id')
                ->get()
                ->contains(fn (Submission $submission): bool => ! $submission->isCompleted());

            throw_if(
                $notReady,
                new AssessmentCannotBePublishedException(
                    'All exams under this schedule must be completed before results can be published.'
                )
            );
        };
    }
}
