<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\GradingScale;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class ExamGradingAction
{
    public function autoGrade(ExamAnswer $answer): void
    {
        $question = $answer->question;
        $type = $question->type instanceof QuestionType ? $question->type : QuestionType::from($question->type);

        if (! in_array($type, [
            QuestionType::McqSingle,
            QuestionType::McqMulti,
            QuestionType::TrueFalse,
            QuestionType::Fill_Blank,
            QuestionType::Short_Answer,
            QuestionType::Matching,
            QuestionType::Ordering,
        ])) {
            return;
        }

        $isCorrect = false;
        $marksAwarded = 0;

        switch ($type) {
            case QuestionType::McqSingle:
            case QuestionType::TrueFalse:
                $isCorrect = $this->gradeSingleChoice($answer, $question);
                break;
            case QuestionType::McqMulti:
                $isCorrect = $this->gradeMultiChoice($answer, $question);
                break;
            case QuestionType::Fill_Blank:
            case QuestionType::Short_Answer:
                $isCorrect = $this->gradeTextOrFillBlankAnswer($answer, $question);
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

    public function gradeTheory(ExamAnswer $answer, float $marks, string $feedback, User $gradedBy): ExamAnswer
    {
        return DB::transaction(function () use ($answer, $marks, $feedback, $gradedBy) {
            $answer->update([
                'marks_awarded' => $marks,
                'teacher_feedback' => $feedback,
                'graded_by' => $gradedBy->id,
            ]);

            return $answer->fresh();
        });
    }

    public function markFullyGraded(ExamAttempt $attempt): ExamAttempt
    {
        $allGraded = $attempt->answers()
            ->whereNull('marks_awarded')
            ->whereHas('question', fn ($q) => $q->whereIn('type', ['essay', 'short_answer']))
            ->doesntExist();

        if (! $allGraded) {
            throw new \RuntimeException('Not all theory answers have been graded.');
        }

        return DB::transaction(function () use ($attempt) {
            $attempt->update([
                'is_theory_graded' => true,
                'status' => ExamAttemptStatus::Graded->value,
            ]);

            return $attempt->fresh();
        });
    }

    public function recomputeScore(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $totalScore = $attempt->answers()->sum('marks_awarded') ?? 0;
            $objectiveScore = $attempt->answers()
                ->whereHas('question', fn ($q) => $q->whereNotIn('type', ['essay']))
                ->sum('marks_awarded') ?? 0;
            $theoryScore = $attempt->answers()
                ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
                ->sum('marks_awarded') ?? 0;

            $exam = $attempt->exam;
            $percentageScore = $exam->total_marks > 0
                ? ($totalScore / $exam->total_marks) * 100
                : 0;

            $grade = null;
            $defaultScale = GradingScale::where('is_default', true)->first();
            if ($defaultScale) {
                foreach ($defaultScale->grades as $g) {
                    if ($percentageScore >= $g['min_score'] && $percentageScore <= $g['max_score']) {
                        $grade = $g['label'];
                        break;
                    }
                }
            }

            $attempt->update([
                'total_score' => $totalScore,
                'percentage_score' => $percentageScore,
                'objective_score' => $objectiveScore,
                'theory_score' => $theoryScore,
                'grade' => $grade,
            ]);

            $exam = $attempt->exam;
            $ungradedAttempts = $exam->attempts()
                ->whereIn('status', [ExamAttemptStatus::Submitted->value, ExamAttemptStatus::Timed_out->value, ExamAttemptStatus::Grading->value])
                ->exists();

            if (! $ungradedAttempts && $exam->status === ExamStatus::Grading->value) {
                $exam->update(['status' => ExamStatus::Completed->value]);
            }

            return $attempt->fresh();
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

    private function gradeTextOrFillBlankAnswer(ExamAnswer $answer, $question): bool
    {
        $studentAnswer = strtolower(trim($answer->text_answer ?? ''));

        $correctAnswers = $question->fillBlankAnswers()->pluck('answer_text')->map(
            fn ($a) => strtolower(trim($a))
        )->toArray();

        if (! empty($correctAnswers)) {
            return in_array($studentAnswer, $correctAnswers);
        }

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
