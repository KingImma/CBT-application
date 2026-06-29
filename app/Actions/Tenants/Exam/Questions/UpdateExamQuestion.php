<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Questions;

use App\Data\Exam\Input\UpdateExamQuestionData;
use App\Exceptions\Domain\Exam\ExamStateTransitionException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use Illuminate\Support\Facades\DB;

class UpdateExamQuestion
{
    public function __construct(
        private RecomputeExamTotalMarks $recomputeMarks
    ) {}

    public function execute(Exam $exam, ExamQuestion $examQuestion, UpdateExamQuestionData $data): ExamQuestion
    {
        $this->ensureExamQuestionIsUpdatable($exam);

        return DB::transaction(fn () => $this->performUpdate($exam, $examQuestion, $data));
    }

    private function ensureExamQuestionIsUpdatable(Exam $exam): void
    {
        throw_unless(
            $exam->isDraft(),
            ExamStateTransitionException::class,
            'Questions can only be modified in draft exams.'
        );
    }

    private function performUpdate(Exam $exam, ExamQuestion $examQuestion, UpdateExamQuestionData $data): ExamQuestion
    {
        $payload = $data->toArray();

        // When marks is explicitly nulled, fall back to the question's default.
        // Load question lazily here — only one question, acceptable cost.
        if (array_key_exists('marks', $payload) && $payload['marks'] === null) {
            $payload['marks'] = $examQuestion->question->default_marks;
        }

        $examQuestion->update($payload);

        $this->recomputeMarks->execute($exam);

        return $examQuestion->fresh();
    }
}