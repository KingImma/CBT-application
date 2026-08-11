<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Listeners;

use App\Domains\Assessments\Events\SubmissionApproved;
use App\Notifications\InAppNotification;

/**
 * Tell the author their paper cleared review — it will go live to students when
 * the assessment activates.
 */
class SendSubmissionApprovedNotification
{
    public function handle(SubmissionApproved $event): void
    {
        $submission = $event->submission;
        $teacher = $submission->teacher;

        if ($teacher === null) {
            return;
        }

        $teacher->notify(new InAppNotification(
            title: 'Submission Approved',
            message: "\"{$submission->title}\" was approved and is ready to go live.",
            type: 'success',
            action: [
                'url' => "/teacher/assessments/{$submission->assessment_id}/submissions/{$submission->id}",
                'label' => 'View Submission',
            ],
        ));
    }
}
