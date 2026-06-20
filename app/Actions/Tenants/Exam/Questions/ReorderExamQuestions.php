<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Questions;

use App\Data\Exam\Input\ReorderQuestionsData;
use App\Exceptions\Domain\Exam\ExamStateTransitionException;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

class ReorderExamQuestions
{
    /**
     * Composed Method: The orchestrator.
     */
    public function execute(Exam $exam, ReorderQuestionsData $data): void
    {
        $this->validateState($exam);

        DB::transaction(fn () => $this->performReorder($exam, $data));
    }

    /**
     * Fail Fast Guard Clauses
     */
    private function validateState(Exam $exam): void
    {
        throw_unless(
            $exam->isDraft(),
            ExamStateTransitionException::class,
            'Questions can only be reordered in draft exams.'
        );
    }

    /**
     * Execution Layer
     */
    private function performReorder(Exam $exam, ReorderQuestionsData $data): void
    {
        // Access the DTO property natively inside the execution layer
        foreach ($data->order as $questionId => $newOrder) {
            $exam->examQuestions()
                ->where('question_id', $questionId)
                ->update(['order' => $newOrder]);
        }
    }
}