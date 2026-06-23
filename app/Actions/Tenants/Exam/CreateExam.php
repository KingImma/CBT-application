<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Data\Exam\Input\CreateExamData;
use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

class CreateExam
{
    public function execute(CreateExamData $data, string $created_by): Exam
    {
        return DB::transaction(fn () => $this->storeExam($data, $created_by));
    }

    private function storeExam(CreateExamData $data, string $created_by): Exam
    {
        return Exam::create([
            'title' => $data->title,
            'subject_id' => $data->subject_id,
            'class_level_id' => $data->class_level_id,
            'class_arm_id' => $data->class_arm_id,
            'term_id' => $data->term_id,
            'created_by' => $created_by,
            'type' => $data->type,
            'status' => ExamStatus::Draft->value,
            'duration_minutes' => $data->duration_minutes,
            'total_marks' => $data->total_marks ?? 0,
            'pass_mark' => $data->pass_mark,
            'max_attempts' => $data->max_attempts ?? 1,
            'scheduled_start' => $data->scheduled_start,
            'instructions' => $data->instructions,
            'settings' => $data->settings?->toArray() ?? [],
        ]);
    }
}
