<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Data\Input\UpdateAssessmentData;
use App\Domains\Assessments\Exceptions\AssessmentStateTransitionException;
use App\Enums\AssessmentStatus;
use App\Models\Tenant\Assessment;
use Illuminate\Support\Facades\DB;

final class UpdateAssessment
{
    public function __construct() {}

    /**
     * Edit the stable definition. Blocked while any occurrence is active or
     * completed — papers and exams already depend on the cap and windows.
     */
    public function execute(Assessment $assessment, UpdateAssessmentData $dto): Assessment
    {
        return DB::transaction(function () use ($assessment, $dto): Assessment {
            $locked = $assessment->schedules()
                ->whereIn('assessment_status', [AssessmentStatus::Active, AssessmentStatus::Completed])
                ->exists();

            throw_if(
                $locked,
                new AssessmentStateTransitionException(
                    'The assessment cannot be edited while one of its schedules is active or completed.'
                )
            );

            $assessment->update($dto->toArray());

            return $assessment->fresh();
        });
    }
}
