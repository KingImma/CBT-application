<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\QuestionType;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\QuestionOption;
use Illuminate\Support\Facades\DB;

class AutoGradeAnswerAction
{
    public function execute(ExamAnswer $answer): void
    {
        $question = $answer->question;
        $type = $question->type instanceof QuestionType ? $question->type : QuestionType::from($question->type);

        // Only auto-grade objective types
        if (! in_array($type, [
            QuestionType::Mcq_Single,
            QuestionType::Mcq_Multi,
            QuestionType::True_False,
            QuestionType::Fill_Blank,
            QuestionType::Short_Answer,
            QuestionType::Matching,
            QuestionType::Ordering,
        ])) {
            return; // Theory questions handled manually
        }

        $isCorrect = false;
        $marksAwarded = 0;

        switch ($type) {
            case QuestionType::Mcq_Single:
            case QuestionType::True_False:
                $isCorrect = $this->gradeSingleChoice($answer, $question);
                break;
            case QuestionType::Mcq_Multi:
                $isCorrect = $this->gradeMultiChoice($answer, $question);
                break;
            case QuestionType::Fill_Blank:
            case QuestionType::Short_Answer:
                $isCorrect = $this->gradeTextAnswer($answer, $question);
                break;
            case QuestionType::Matching:
                $isCorrect = $this->gradeMatching($answer, $question);
                break;
            case QuestionType::Ordering:
                $isCorrect = $this->gradeOrdering($answer, $question);
                break;
        }

        if ($isCorrect) {
            $marksAwarded = $answer->attempt->exam->examQuestions()
                ->where('question_id', $question->id)
                ->first()
                ?->getEffectiveMarks() ?? $question->default_marks;
        }

        DB::transaction(function () use ($answer, $isCorrect, $marksAwarded) {
            $answer->update([
                'is_correct' => $isCorrect,
                'marks_awarded' => $marksAwarded,
            ]);
        });
    }

    private function gradeSingleChoice(ExamAnswer $answer, $question): bool
    {
        $selected = $answer->selected_option_ids ?? [];
        if (count($selected) !== 1) {
            return false;
        }

        $correctOption = $question->options()->where('is_correct', true)->first();
        return $correctOption && $selected[0] === $correctOption->id;
    }

    private function gradeMultiChoice(ExamAnswer $answer, $question): bool
    {
        $selected = $answer->selected_option_ids ?? [];
        $correctIds = $question->options()->where('is_correct', true)->pluck('id')->sort()->values()->toArray();
        $selectedSorted = collect($selected)->sort()->values()->toArray();

        return $correctIds === $selectedSorted;
    }

    private function gradeTextAnswer(ExamAnswer $answer, $question): bool
    {
        $studentAnswer = strtolower(trim($answer->text_answer ?? ''));
        
        // Check fill_blank_answers first
        $correctAnswers = $question->fillBlankAnswers()->pluck('answer_text')->map(
            fn ($a) => strtolower(trim($a))
        )->toArray();

        if (! empty($correctAnswers)) {
            return in_array($studentAnswer, $correctAnswers);
        }

        // Fallback: check options (for true/false style)
        $correctOption = $question->options()->where('is_correct', true)->first();
        if ($correctOption) {
            return $studentAnswer === strtolower(trim($correctOption->content));
        }

        return false;
    }

    private function gradeMatching(ExamAnswer $answer, $question): bool
    {
        $studentAnswer = $answer->matching_answer ?? [];
        $correctPairs = $question->options()->whereNotNull('match_pair')
            ->pluck('match_pair', 'id')
            ->toArray();

        if (count($studentAnswer) !== count($correctPairs)) {
            return false;
        }

        foreach ($studentAnswer as $optionId => $matchValue) {
            if (! isset($correctPairs[$optionId]) || $correctPairs[$optionId] !== $matchValue) {
                return false;
            }
        }

        return true;
    }

    private function gradeOrdering(ExamAnswer $answer, $question): bool
    {
        $studentOrder = $answer->ordering_answer ?? [];
        $correctOrder = $question->options()->orderBy('order')->pluck('id')->toArray();

        return $studentOrder === $correctOrder;
    }
}
