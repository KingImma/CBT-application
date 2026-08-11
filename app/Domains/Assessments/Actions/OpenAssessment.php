<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Events\AssessmentOpened;
use App\Models\Tenant\Assessment;
use Illuminate\Support\Facades\DB;

final class OpenAssessment
{
    public function __construct() {}

    public function execute(Assessment $assessment): Assessment
    {
        return DB::transaction(function () use ($assessment): Assessment {
            $assessment->open();

            $fresh = $assessment->fresh();

            event(new AssessmentOpened($fresh));

            return $fresh;
        });
    }
}
