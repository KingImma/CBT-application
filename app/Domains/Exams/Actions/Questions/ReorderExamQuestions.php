<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Questions;

use App\Domains\Exams\Data\Input\ReorderQuestionsData;
use App\Domains\Exams\Support\ExamQuestionRules;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

/**
 * Bulk re-order â€” operates on N rows simultaneously.
 * Base UpdateAction owns a single Model; this owns a collection.
 * Plain class + DB::transaction is the laziest correct solution.
 */
final class ReorderExamQuestions
{
    public function execute(Exam $exam, ReorderQuestionsData $data): void
    {
        ExamQuestionRules::isDraft('Questions can only be reordered in draft exams.')($exam);

        DB::transaction(function () use ($exam, $data) {
            foreach ($data->order as $questionId => $newOrder) {
                $updated = $exam->examQuestions()
                    ->where('question_id', $questionId)
                    ->update(['order' => $newOrder]);

                throw_if($updated === 0, new \DomainException('Question does not belong to this exam.'));
            }
        });
    }
}
