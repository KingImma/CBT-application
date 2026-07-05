<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Questions;

use App\Actions\Base\DeleteAction;
use App\Actions\Tenants\Exam\ExamQuestionGuards;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;

final class DeleteExamQuestion
{
    public function __construct(
        private DeleteAction $action,
        private RecomputeExamTotalMarks $recompute,
    ) {}

    public function execute(Exam $exam, Question $question): void
    {
        ExamQuestionGuards::isDraft('Questions can only be removed from draft exams.')($exam);

        $examQuestion = $exam->examQuestions()
            ->where('question_id', $question->id)
            ->firstOrFail();

        $this->action->execute(
            $examQuestion,
            guard: fn ($eq) => null,  // guard ran above
            after: fn ($eq) => $this->recompute->execute($exam),
        );
    }
}
