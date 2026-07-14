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
    public function __construct(private RecomputeExamTotalMarks $recompute) {}

    public function execute(Exam $exam, Question $question, UpdateExamQuestionData $data): ExamQuestion
    {
        ExamQuestionRules::isDraft('Questions can only be modified in a draft exam.')($exam);

        $examQuestion = $exam->examQuestions()
            ->where('question_id', $question->id)
            ->firstOrFail();

        return DB::transaction(function () use ($examQuestion, $exam, $question, $data) {
            $examQuestion->update(array_filter([
                'marks' => $data->marks ?? $question->default_marks,
                'order' => $data->order ?? $examQuestion->order,
            ], fn ($v) => $v !== null));

            $this->recompute->execute($exam);

            return $examQuestion->fresh();
        });
    }
}
