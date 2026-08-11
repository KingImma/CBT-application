<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Data\Input\UpdateAssessmentData;
use App\Domains\Assessments\Exceptions\AssessmentStateTransitionException;
use App\Models\Tenant\Assessment;
use Illuminate\Support\Facades\DB;

final class UpdateAssessment
{
    public function __construct() {}

    public function execute(Assessment $assessment, UpdateAssessmentData $dto): Assessment
    {
        return DB::transaction(function () use ($assessment, $dto): Assessment {
            throw_unless(
                $assessment->isDraft(),
                new AssessmentStateTransitionException(
                    'Only draft assessments can be edited.'
                )
            );

            $assessment->update($dto->toArray());

            return $assessment->fresh();
        });
    }
}
