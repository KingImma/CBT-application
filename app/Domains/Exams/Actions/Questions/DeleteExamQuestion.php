<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Questions;

use App\Domains\Exams\Support\ExamQuestionRules;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use Illuminate\Support\Facades\DB;

final class DeleteExamQuestion
{
    public function __construct(private RecomputeExamTotalMarks $recompute) {}

    public function execute(Exam $exam, Question $question): void
    {
        ExamQuestionRules::isDraft('Questions can only be removed from draft exams.')($exam);

        $examQuestion = $exam->examQuestions()
            ->where('question_id', $question->id)
            ->firstOrFail();

        DB::transaction(function () use ($examQuestion, $exam) {
            $examQuestion->delete();
            $this->recompute->execute($exam);
        });
    }
}
