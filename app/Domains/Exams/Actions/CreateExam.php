<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions;

use App\Domains\Exams\Data\Input\CreateExamData;
use App\Enums\ExamStatus;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevelSubject;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateExam
{
    public function __construct() {}

    public function execute(CreateExamData $dto, string $createdBy): Exam
    {
        $this->ensureValidRelationships($dto);

        return DB::transaction(function () use ($dto, $createdBy) {
            return Exam::create([
                'title' => $dto->title,
                'subject_id' => $dto->subject_id,
                'class_level_id' => $dto->class_level_id,
                'class_arm_id' => $dto->class_arm_id,
                'term_id' => $dto->term_id,
                'created_by' => $createdBy,
                'type' => $dto->type,
                'status' => ExamStatus::Draft->value,
                'duration_minutes' => $dto->duration_minutes,
                'total_marks' => $dto->total_marks ?? 0,
                'pass_mark' => $dto->pass_mark,
                'max_attempts' => $dto->max_attempts ?? 1,
                'scheduled_start' => $dto->scheduled_start,
                'instructions' => $dto->instructions,
                'settings' => $dto->settings?->toArray() ?? [],
            ]);
        });
    }

    private function ensureValidRelationships(CreateExamData $dto): void
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

        throw_if(
            ! ClassLevelSubject::where('class_level_id', $dto->class_level_id)
                ->where('subject_id', $dto->subject_id)
                ->exists(),
            ValidationException::withMessages([
                'subject_id' => ['The selected subject is not assigned to the selected class level.'],
            ])
        );
    }
}
