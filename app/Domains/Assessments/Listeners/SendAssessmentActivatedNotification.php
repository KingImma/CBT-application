<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Listeners;

use App\Domains\Assessments\Events\AssessmentActivated;
use App\Models\Tenant\StudentProfile;
use App\Notifications\InAppNotification;

/**
 * The schedule went live: its approved submissions are now materialised into
 * student-facing exams. Notify every student in the target class (level, and
 * arm when the assessment is arm-scoped) that they can start.
 */
class SendAssessmentActivatedNotification
{
    public function handle(AssessmentActivated $event): void
    {
        $schedule = $event->schedule;
        $assessment = $schedule->assessment;

        StudentProfile::query()
            ->where('class_level_id', $schedule->class_level_id)
            ->when(
                $schedule->class_arm_id,
                fn ($q) => $q->where('class_arm_id', $schedule->class_arm_id),
            )
            ->with('user')
            ->chunk(100, function ($students) use ($assessment): void {
                foreach ($students as $student) {
                    $student->user?->notify(new InAppNotification(
                        title: 'Assessment Now Available',
                        message: "\"{$assessment->title}\" is now open. Start before the window closes.",
                        type: 'info',
                        action: [
                            'url' => '/student/exams',
                            'label' => 'Start Assessment',
                        ],
                    ));
                }
            });
    }
}
