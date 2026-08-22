<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Data\Input\CreateAssessmentData;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\ClassArm;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateAssessment
{
    public function __construct() {}

    /**
     * Create the stable assessment definition. No term, windows or status —
     * occurrences live on AssessmentSchedule rows.
     */
    public function execute(CreateAssessmentData $dto, string $createdBy): Assessment
    {
        $this->ensureValidRelationships($dto);

        return DB::transaction(fn (): Assessment => Assessment::create([
            'title' => $dto->title,
            'class_level_id' => $dto->class_level_id,
            'class_arm_id' => $dto->class_arm_id,
            'created_by' => $createdBy,
            'total_marks' => $dto->total_marks,
            'duration_minutes' => $dto->duration_minutes,
            'description' => $dto->description,
        ]));
    }

    private function ensureValidRelationships(CreateAssessmentData $dto): void
    {
        throw_if(
            $dto->class_arm_id &&
            ! ClassArm::whereKey($dto->class_arm_id)
                ->where('class_level_id', $dto->class_level_id)
                ->exists(),
            ValidationException::withMessages([
                'class_arm_id' => ['The selected class arm does not belong to the selected class level.'],
            ])
        );
    }
}
