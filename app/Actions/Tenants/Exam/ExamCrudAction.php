<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

class ExamCrudAction
{
    public function create(array $data): Exam
    {
        return DB::transaction(function () use ($data) {
            return Exam::create([
                'title' => $data['title'],
                'subject_id' => $data['subject_id'],
                'class_level_id' => $data['class_level_id'],
                'class_arm_id' => $data['class_arm_id'] ?? null,
                'term_id' => $data['term_id'],
                'created_by' => $data['created_by'],
                'type' => $data['type'],
                'status' => 'draft',
                'duration_minutes' => $data['duration_minutes'],
                'total_marks' => $data['total_marks'] ?? 0,
                'pass_mark' => $data['pass_mark'] ?? null,
                'max_attempts' => $data['max_attempts'] ?? 1,
                'scheduled_start' => $data['scheduled_start'] ?? null,
                'scheduled_end' => $data['scheduled_end'] ?? null,
                'settings' => $data['settings'] ?? [],
                'instructions' => $data['instructions'] ?? null,
            ]);
        });
    }

    public function update(Exam $exam, array $data): Exam
    {
        if ($exam->status !== 'draft') {
            throw new \RuntimeException('Only draft exams can be updated.');
        }

        return DB::transaction(function () use ($exam, $data) {
            $exam->update($data);

            return $exam->fresh();
        });
    }

    public function delete(Exam $exam): void
    {
        if ($exam->status !== 'draft') {
            throw new \RuntimeException('Only draft exams can be deleted.');
        }

        DB::transaction(function () use ($exam) {
            $exam->delete();
        });
    }
}
