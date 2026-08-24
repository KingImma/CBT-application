<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Exceptions\AssessmentStateTransitionException;
use App\Models\Tenant\AssessmentSchedule;
use Illuminate\Support\Facades\DB;

final class DeleteAssessmentSchedule
{
    public function __construct() {}

    /** Only draft occurrences can be deleted; activation locks the schedule. */
    public function execute(AssessmentSchedule $schedule): void
    {
        DB::transaction(function () use ($schedule): void {
            throw_unless(
                $schedule->isDraft(),
                new AssessmentStateTransitionException(
                    'Only draft schedules can be deleted.'
                )
            );

            $schedule->delete();
        });
    }
}
