<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Exceptions\AssessmentCannotBeOpenedException;
use App\Models\Tenant\Assessment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReopenAssessment
{
    public function __construct() {}

    /**
     * Reopen a closed submission window for stragglers (decisions #2/#8).
     * A new strictly-future close time is required.
     */
    public function execute(Assessment $assessment, string $submissionClosesAt): Assessment
    {
        return DB::transaction(function () use ($assessment, $submissionClosesAt): Assessment {
            $newClose = Carbon::parse($submissionClosesAt);

            throw_unless(
                $newClose->isFuture(),
                new AssessmentCannotBeOpenedException(
                    'A new submission close time in the future must be provided to reopen.'
                )
            );

            $assessment->submission_closes_at = $newClose;
            $assessment->reopen();

            return $assessment->fresh();
        });
    }
}
