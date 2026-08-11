<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Data\Input\CreateAssessmentData;
use App\Enums\AssessmentStatus;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\ClassArm;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateAssessment
{
    public function __construct() {}

    public function execute(CreateAssessmentData $dto, string $createdBy): Assessment
    {
        $this->ensureValidRelationships($dto);

        return DB::transaction(fn (): Assessment => Assessment::create([
            'title' => $dto->title,
            'class_level_id' => $dto->class_level_id,
            'class_arm_id' => $dto->class_arm_id,
            'term_id' => $dto->term_id,
            'created_by' => $createdBy,
            'status' => AssessmentStatus::Draft->value,
            'total_marks' => $dto->total_marks,
            'duration_minutes' => $dto->duration_minutes,
            'submission_opens_at' => $dto->submission_opens_at,
            'submission_closes_at' => $dto->submission_closes_at,
            'student_starts_at' => $dto->student_starts_at,
            'student_ends_at' => $dto->student_ends_at,
            'instructions' => $dto->instructions,
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
