<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Listeners;

use App\Domains\Assessments\Events\AssessmentOpened;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\User;
use App\Notifications\InAppNotification;

/**
 * Tell every teacher eligible to author here (a subject assignment on the
 * assessment's class level — decision #1) that the submission window is open.
 */
class SendAssessmentOpenedNotification
{
    public function handle(AssessmentOpened $event): void
    {
        $assessment = $event->assessment;

        $teacherIds = TeacherSubjectAssignment::query()
            ->where('class_level_id', $assessment->class_level_id)
            ->distinct()
            ->pluck('user_id');

        if ($teacherIds->isEmpty()) {
            return;
        }

        $deadline = $assessment->submission_closes_at?->format('M j, Y g:i A');

        User::query()
            ->whereIn('id', $teacherIds)
            ->where('is_active', true)
            ->get()
            ->each(fn (User $teacher) => $teacher->notify(new InAppNotification(
                title: 'Assessment Open for Submissions',
                message: $deadline
                    ? "\"{$assessment->title}\" is open. Submit your paper before {$deadline}."
                    : "\"{$assessment->title}\" is open for submissions.",
                type: 'info',
                action: [
                    'url' => "/teacher/assessments/{$assessment->id}",
                    'label' => 'View Assessment',
                ],
            )));
    }
}
