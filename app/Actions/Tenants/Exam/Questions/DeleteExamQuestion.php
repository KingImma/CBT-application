<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Questions;

use App\Exceptions\Domain\Exam\ExamStateTransitionException;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use Illuminate\Support\Facades\DB;

class DeleteExamQuestion
{
    public function __construct(
        private RecomputeExamTotalMarks $recomputeMarks
    ) {}

    public function execute(Exam $exam, ExamQuestion $examQuestion): void
    {
        $this->ensureExamQuestionIsDeletable($exam);

        DB::transaction(fn () => $this->performDeletion($exam, $examQuestion));
    }

    private function ensureExamQuestionIsDeletable(Exam $exam): void
    {
        throw_unless(
            $exam->isDraft(),
            ExamStateTransitionException::class,
            'Questions can only be removed from draft exams.'
        );
    }

    private function performDeletion(Exam $exam, ExamQuestion $examQuestion): void
    {
        $examQuestion->delete();

        $this->recomputeMarks->execute($exam);
    }
}