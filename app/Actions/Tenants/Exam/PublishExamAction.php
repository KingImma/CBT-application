<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamType;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Topic;
use Illuminate\Support\Facades\DB;

class PublishExamAction
{
    public function execute(Exam $exam): Exam
    {
        if ($exam->status !== 'draft') {
            throw new \RuntimeException('Only draft exams can be published.');
        }

        if ($exam->examQuestions()->count() === 0) {
            throw new \RuntimeException('Exam must have at least one question.');
        }

        if ((float) $exam->total_marks <= 0) {
            throw new \RuntimeException('Exam total marks must be greater than 0.');
        }

        if ($exam->duration_minutes <= 0) {
            throw new \RuntimeException('Exam duration must be greater than 0.');
        }

        if ($exam->pass_mark === null) {
            throw new \RuntimeException('Exam pass mark must be set.');
        }

        if ((float) $exam->pass_mark > (float) $exam->total_marks) {
            throw new \RuntimeException('Pass mark cannot exceed total marks.');
        }

        if ($exam->topics()->count() === 0) {
            throw new \RuntimeException('Exam must have at least one topic in the pool.');
        }

        return DB::transaction(function () use ($exam) {
            // For exam type, force show_result_immediately = false
            $settings = $exam->settings ?? [];
            if ($exam->type === ExamType::Exam->value) {
                $settings['show_result_immediately'] = false;
            }

            $exam->update([
                'status' => 'scheduled',
                'settings' => $settings,
            ]);

            return $exam->fresh();
        });
    }
}
