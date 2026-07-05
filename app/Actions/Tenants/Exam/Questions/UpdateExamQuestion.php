<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Questions;

use App\Actions\Base\UpdateAction;
use App\Actions\Tenants\Exam\ExamQuestionGuards;
use App\Data\Exam\Input\UpdateExamQuestionData;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;

final class UpdateExamQuestion
{
    public function __construct(
        private UpdateAction $action,
        private RecomputeExamTotalMarks $recompute,
    ) {}

    public function execute(Exam $exam, Question $question, UpdateExamQuestionData $data): ExamQuestion
    {
        ExamQuestionGuards::isDraft('Questions can only be modified in a draft exam.')($exam);

        $examQuestion = $exam->examQuestions()
            ->where('question_id', $question->id)
            ->firstOrFail();

        return $this->action->execute(
            $examQuestion,
            ['exam' => $exam, 'question' => $question, 'data' => $data],
            guard: fn ($eq, $d) => null,
            prepare: fn (ExamQuestion $eq, array $d) => array_filter([
                'marks' => $d['data']->marks ?? $d['question']->default_marks,
                'order' => $d['data']->order ?? $eq->order,
            ], fn ($v) => $v !== null),
            after: fn (ExamQuestion $eq, array $d) => $this->recompute->execute($d['exam'])
        );
    }
}
