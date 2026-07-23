<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Questions;

use App\Domains\Exams\Data\Input\UpdateExamQuestionData;
use App\Domains\Exams\Support\ExamQuestionRules;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use Illuminate\Support\Facades\DB;

final class UpdateExamQuestion
{
    public function __construct(private RecomputeExamTotalMarks $recomputeTotalMarks) {}

    public function execute(Exam $exam, Question $question, UpdateExamQuestionData $updateData): ExamQuestion
    {
        // Guard: only draft exams allows question modification
        ExamQuestionRules::isDraft('Questions can only be modified in a draft exam.')($exam);

        // locate the exam-Question pivot for this question
        $examQuestionPivot = $exam->examQuestions()
            ->where('question_id', $question->id)
            ->firstOrFail();

        return DB::transaction(function () use ($examQuestionPivot, $exam, $question, $updateData) {
            $examQuestionPivot->update(array_filter([
                'marks' => $updateData->marks ?? $question->default_marks,
                'order' => $updateData->order ?? $examQuestionPivot->order,
            ], fn ($value) => $value !== null));

            $this->recomputeTotalMarks->execute($exam);

            return $examQuestionPivot->fresh();
        });
    }
}
