<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Questions;

use App\Exceptions\Domain\Exam\ExamStateTransitionException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use Illuminate\Support\Facades\DB;

class DeleteExamQuestion
{
    public function __construct(
        private RecomputeExamTotalMarks $recomputeMarks
    ) {}

    public function execute(Exam $exam, Question $question): void
    {
        $this->ensureExamQuestionIsDeletable($exam);

        DB::transaction(fn () => $this->performDeletion($exam, $question));
    }

    private function ensureExamQuestionIsDeletable(Exam $exam): void
    {
        throw_unless(
            $exam->isDraft(),
            ExamStateTransitionException::class,
            'Questions can only be removed from draft exams.'
        );
    }

    private function performDeletion(Exam $exam, Question $question): void
    {
        $examQuestion = $exam->examQuestions()
            ->where('question_id', $question->id)
            ->firstOrFail();
    
        $examQuestion->delete();
    
        $this->recomputeMarks->execute($exam);
    }
}
