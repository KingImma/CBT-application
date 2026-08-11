<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Models\Tenant\Assessment;
use Illuminate\Support\Facades\DB;

final class CompleteAssessment
{
    public function __construct() {}

    public function execute(Assessment $assessment): Assessment
    {
        return DB::transaction(function () use ($assessment): Assessment {
            $assessment->complete();

            return $assessment->fresh();
        });
    }
}
