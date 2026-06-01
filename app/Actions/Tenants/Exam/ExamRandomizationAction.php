<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ExamRandomizationAction
{
    public function __construct(
        private ExamQuestionManagementAction $managementAction,
    ) {}

    public function randomizeQuestions(Exam $exam, int $count): void
    {
        if (! in_array($exam->status, [ExamStatus::Draft, ExamStatus::Scheduled])) {
            throw new \RuntimeException('Questions can only be added to draft or scheduled exams.');
        }

        $availableCount = $this->getAvailableQuestionsQuery($exam)->count();

        if ($availableCount < $count) {
            throw new \RuntimeException(
                "Only {$availableCount} questions available, but {$count} requested."
            );
        }

        DB::transaction(function () use ($exam, $count) {
            $questions = $this->getAvailableQuestionsQuery($exam)
                ->inRandomOrder()
                ->limit($count)
                ->get();

            $maxOrder = $exam->examQuestions()->max('order') ?? 0;

            foreach ($questions as $index => $question) {
                $exam->examQuestions()->create([
                    'question_id' => $question->id,
                    'order' => $maxOrder + $index + 1,
                    'marks' => $question->default_marks,
                ]);
            }

            $this->managementAction->recomputeTotalMarks($exam);
        });
    }

    private function getAvailableQuestionsQuery(Exam $exam): Builder
    {
        return Question::where('subject_id', $exam->subject_id)
            ->where('class_level_id', $exam->class_level_id)
            ->where('is_active', true);
    }
}
