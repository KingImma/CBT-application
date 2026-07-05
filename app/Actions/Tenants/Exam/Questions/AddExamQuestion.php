<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Questions;

use App\Actions\Base\CreateAction;
use App\Actions\Tenants\Exam\ExamQuestionGuards;
use App\Data\Exam\Input\AddQuestionData;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;

final class AddExamQuestion
{
    public function __construct(
        private CreateAction $action,
        private RecomputeExamTotalMarks $recompute,
    ) {}

    public function execute(Exam $exam, Question $question, AddQuestionData $data, string $userId): ExamQuestion
    {
        ExamQuestionGuards::ownsQuestion($question, $userId)($exam);
        ExamQuestionGuards::isDraft('Questions can only be added to a draft exam.')($exam);
        ExamQuestionGuards::notDuplicate($question)($exam);

        /** @var ExamQuestion $examQuestion */
        $examQuestion = $this->action->execute(
            ExamQuestion::class,
            ['exam' => $exam, 'question' => $question, 'data' => $data],
            prepare: fn (array $d) => [
                'exam_id' => $d['exam']->id,
                'question_id' => $d['question']->id,
                'order' => ($d['exam']->examQuestions()->max('order') ?? 0) + 1,
                'marks' => $d['data']->marks_override ?? $d['question']->default_marks,
            ],
            after: fn (ExamQuestion $eq, array $d) => $this->recompute->execute($d['exam']),
        );

        return $examQuestion;
    }
}
