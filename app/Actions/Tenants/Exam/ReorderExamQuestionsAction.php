<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use Illuminate\Support\Facades\DB;

class ReorderExamQuestionsAction
{
    public function execute(Exam $exam, array $orderMapping): void
    {
        if ($exam->status !== 'draft') {
            throw new \RuntimeException('Questions can only be reordered in draft exams.');
        }

        DB::transaction(function () use ($exam, $orderMapping) {
            foreach ($orderMapping as $questionId => $newOrder) {
                ExamQuestion::where('exam_id', $exam->id)
                    ->where('question_id', $questionId)
                    ->update(['order' => $newOrder]);
            }
        });
    }
}
