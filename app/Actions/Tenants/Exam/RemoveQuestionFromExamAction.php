<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use Illuminate\Support\Facades\DB;

class RemoveQuestionFromExamAction
{
    public function execute(Exam $exam, string $questionId): void
    {
        if (! in_array($exam->status, ['draft', 'scheduled'])) {
            throw new \RuntimeException('Questions can only be removed from draft or scheduled exams.');
        }

        DB::transaction(function () use ($exam, $questionId) {
            $examQuestion = ExamQuestion::where('exam_id', $exam->id)
                ->where('question_id', $questionId)
                ->firstOrFail();

            $examQuestion->delete();

            $this->recomputeTotalMarks($exam);
        });
    }

    private function recomputeTotalMarks(Exam $exam): void
    {
        $total = $exam->examQuestions()->get()->sum(fn ($eq) => $eq->getEffectiveMarks());
        $exam->update(['total_marks' => $total]);
    }
}
