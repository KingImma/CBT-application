<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Questions;

use App\Data\Exam\Input\UpdateExamQuestionData;
use App\Exceptions\Domain\Exam\ExamStateTransitionException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use Illuminate\Support\Facades\DB;

class UpdateExamQuestion
{
    public function __construct(
        private RecomputeExamTotalMarks $recomputeMarks,
    ) {}

    public function execute(
        Exam $exam,
        Question $question,
        UpdateExamQuestionData $data,
    ): ExamQuestion {
        $this->ensureExamQuestionIsUpdatable($exam);

        return DB::transaction(
            fn() => $this->performUpdate($exam, $question, $data),
        );
    }

    private function ensureExamQuestionIsUpdatable(Exam $exam): void
    {
        throw_unless(
            $exam->isDraft(),
            ExamStateTransitionException::class,
            "Questions can only be modified in draft exams.",
        );
    }

    private function performUpdate(
        Exam $exam,
        Question $question,
        UpdateExamQuestionData $data,
    ): ExamQuestion {
        $examQuestion = $exam
            ->examQuestions()
            ->where("question_id", $question->id)
            ->firstOrFail();

        $payload = $data->toArray();

        if (array_key_exists("marks", $payload) && $payload["marks"] === null) {
            $payload["marks"] = $question->default_marks;
        }

        $examQuestion->update($payload);

        $this->recomputeMarks->execute($exam);

        return $examQuestion->fresh();
    }
}
