<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use App\Models\Tenant\ExamQuestion;
use Illuminate\Support\Facades\DB;

class AddQuestionToExamAction
{
    public function execute(Exam $exam, string $questionId, ?string $marksOverride = null): ExamQuestion
    {
        if (! in_array($exam->status, ['draft', 'scheduled'])) {
            throw new \RuntimeException('Questions can only be added to draft or scheduled exams.');
        }

        $question = Question::findOrFail($questionId);

        $maxOrder = $exam->examQuestions()->max('order') ?? 0;

        return DB::transaction(function () use ($exam, $question, $marksOverride, $maxOrder) {
            $examQuestion = ExamQuestion::create([
                'exam_id' => $exam->id,
                'question_id' => $question->id,
                'order' => $maxOrder + 1,
                'marks_override' => $marksOverride,
            ]);

            $this->recomputeTotalMarks($exam);

            return $examQuestion;
        });
    }

    private function recomputeTotalMarks(Exam $exam): void
    {
        $total = $exam->examQuestions()->get()->sum(fn ($eq) => $eq->getEffectiveMarks());
        $exam->update(['total_marks' => $total]);
    }
}
