<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Listeners;

use App\Domains\Assessments\Events\SubmissionChangesRequested;
use App\Notifications\InAppNotification;

/**
 * Return the paper to its author with the reviewer's note. Dispatched inside the
 * requestChanges transaction, so the teacher is only told once the flip commits.
 */
class SendSubmissionChangesRequestedNotification
{
    public function handle(SubmissionChangesRequested $event): void
    {
        $submission = $event->submission;
        $teacher = $submission->teacher;

        if ($teacher === null) {
            return;
        }

        $teacher->notify(new InAppNotification(
            title: 'Changes Requested on Your Submission',
            message: "{$event->admin->first_name} {$event->admin->last_name} requested changes on \"{$submission->title}\".",
            type: 'warning',
            action: [
                'url' => "/teacher/assessments/{$submission->assessment_id}/submissions/{$submission->id}",
                'label' => 'View Feedback',
            ],
        ));
    }
}
