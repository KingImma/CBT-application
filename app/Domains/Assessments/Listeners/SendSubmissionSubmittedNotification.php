<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Listeners;

use App\Domains\Assessments\Events\SubmissionSubmitted;
use App\Enums\RoleType;
use App\Models\Tenant\User;
use App\Notifications\InAppNotification;

/**
 * A teacher sent a paper up the review chain — notify every school admin so the
 * review loop can begin.
 */
class SendSubmissionSubmittedNotification
{
    public function handle(SubmissionSubmitted $event): void
    {
        $submission = $event->submission;
        $submission->loadMissing('teacher');
        $teacherName = trim("{$submission->teacher?->first_name} {$submission->teacher?->last_name}");

        User::query()
            ->where('role', RoleType::SchoolAdmin->value)
            ->where('is_active', true)
            ->get()
            ->each(fn (User $admin) => $admin->notify(new InAppNotification(
                title: 'Submission Ready for Review',
                message: "{$teacherName} submitted \"{$submission->title}\" for review.",
                type: 'info',
                action: [
                    'url' => "/admin/assessments/{$submission->assessment_schedule_id}/teacher-submissions/{$submission->id}",
                    'label' => 'Review Submission',
                ],
            )));
    }
}
