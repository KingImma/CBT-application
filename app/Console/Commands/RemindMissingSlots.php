<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NotificationLabel;
use App\Enums\SubmissionStatus;
use App\Models\Tenant;
use App\Models\Tenant\AssessmentSchedule;
use App\Notifications\InAppNotification;
use Illuminate\Console\Command;

class RemindMissingSlots extends Command
{
    protected $signature = 'assessments:remind-missing-slots {--tenant= : Restrict to one tenant id}';

    protected $description = 'Nudge teachers whose approved subject has no exam slot on a draft schedule';

    public function handle(): int
    {
        $tenants = Tenant::query()
            ->where('is_active', true)
            ->when($this->option('tenant'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        $reminded = 0;

        foreach ($tenants as $tenant) {
            $tenant->run(function () use (&$reminded): void {
                AssessmentSchedule::query()
                    ->where('assessment_status', 'draft')
                    ->with([
                        'assessment',
                        'submissions' => fn ($q) => $q->where('status', SubmissionStatus::Approved->value),
                    ])
                    ->chunk(50, function ($schedules) use (&$reminded): void {
                        foreach ($schedules as $schedule) {
                            $missing = $schedule->submissions->filter(
                                fn ($submission) => ! $schedule->scheduleSubjects()
                                    ->where('subject_id', $submission->subject_id)
                                    ->exists()
                            );

                            foreach ($missing as $submission) {
                                $submission->teacher?->notify(new InAppNotification(
                                    title: 'Place your subject on the timetable',
                                    message: "Choose a day and time for {$submission->subject?->name} in \"{$schedule->assessment?->title}\".",
                                    type: 'warning',
                                    label: NotificationLabel::Assessment->value,
                                    action: [
                                        'url' => "/teacher/submissions/{$submission->id}",
                                        'label' => 'Schedule Subject',
                                    ],
                                ));
                                $reminded++;
                            }
                        }
                    });
            });
        }

        $this->info("Reminded {$reminded} teacher(s).");

        return self::SUCCESS;
    }
}