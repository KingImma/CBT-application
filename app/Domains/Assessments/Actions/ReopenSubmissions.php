<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Models\Tenant\AssessmentSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReopenSubmissions
{
    public function __construct() {}

    /**
     * Reopen a closed question window for stragglers. A new strictly-future
     * close time is required.
     */
    public function execute(AssessmentSchedule $schedule, string $questionSubmissionEnds): AssessmentSchedule
    {
        return DB::transaction(function () use ($schedule, $questionSubmissionEnds): AssessmentSchedule {
            $newClose = Carbon::parse($questionSubmissionEnds);

            throw_unless(
                $newClose->isFuture(),
                new AssessmentCannotBeOpenedException(
                    'A new question submission deadline in the future must be provided to reopen.'
                )
            );

            $schedule->question_submission_ends = $newClose;
            $schedule->reopenSubmissions();

            return $schedule->fresh();
        });
    }
}
