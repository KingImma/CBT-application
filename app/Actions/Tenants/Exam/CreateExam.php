<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Actions\Base\CreateAction;
use App\Data\Exam\Input\CreateExamData;
use App\Enums\ExamStatus;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevelSubject;
use App\Models\Tenant\Exam;
use Illuminate\Validation\ValidationException;

final class CreateExam
{
    public function __construct(private CreateAction $action) {}

    public function execute(CreateExamData $dto, string $createdBy): Exam
    {
        $this->ensureValidRelationships($dto);

        return $this->action->execute(
            Exam::class,
            ['dto' => $dto, 'created_by' => $createdBy],
            prepare: fn (array $d) => [
                'title' => $d['dto']->title,
                'subject_id' => $d['dto']->subject_id,
                'class_level_id' => $d['dto']->class_level_id,
                'class_arm_id' => $d['dto']->class_arm_id,
                'term_id' => $d['dto']->term_id,
                'created_by' => $d['created_by'],
                'type' => $d['dto']->type,
                'status' => ExamStatus::Draft->value,
                'duration_minutes' => $d['dto']->duration_minutes,
                'total_marks' => $d['dto']->total_marks ?? 0,
                'pass_mark' => $d['dto']->pass_mark,
                'max_attempts' => $d['dto']->max_attempts ?? 1,
                'scheduled_start' => $d['dto']->scheduled_start,
                'instructions' => $d['dto']->instructions,
                'settings' => $d['dto']->settings?->toArray() ?? [],
            ],
        );
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
