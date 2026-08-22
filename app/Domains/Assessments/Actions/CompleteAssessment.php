<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Models\Tenant\AssessmentSchedule;
use Illuminate\Support\Facades\DB;

final class CompleteAssessment
{
    public function __construct() {}

    public function execute(AssessmentSchedule $schedule): AssessmentSchedule
    {
        return DB::transaction(function () use ($schedule): AssessmentSchedule {
            $schedule->complete();

            return $schedule->fresh();
        });
    }
}
