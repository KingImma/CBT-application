<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\GradingScale;
use App\Models\Tenant\Question;
use Illuminate\Support\Facades\DB;

class ExamGradingAction
{
    public function autoGrade(ExamAnswer $answer): void
    {
        $question = $answer->question;
        $isCorrect = $this->gradeSingleChoice($answer, $question);
        $marksAwarded = 0;

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

    public function recomputeScore(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $totalScore = $this->calculateTotalScore($attempt);
            $percentageScore = $this->calculatePercentageScore($attempt->exam, $totalScore);
            $grade = $this->resolveGrade($percentageScore);

            $attempt->update([
                'total_score' => $totalScore,
                'percentage_score' => $percentageScore,
                'objective_score' => $totalScore,
                'theory_score' => 0,
                'grade' => $grade,
            ]);

            return $attempt->fresh();
        });
    }

    private function calculateTotalScore(ExamAttempt $attempt): float
    {
        return $attempt->answers()->sum('marks_awarded') ?? 0;
    }

    private function calculatePercentageScore(Exam $exam, float $totalScore): float
    {
        return $exam->total_marks > 0
            ? ($totalScore / $exam->total_marks) * 100
            : 0;
    }

    private function resolveGrade(float $percentageScore): ?string
    {
        $defaultScale = GradingScale::where('is_default', true)->first();
        if (! $defaultScale) {
            return null;
        }

        foreach ($defaultScale->grades as $grade) {
            if ($percentageScore >= $grade['min_score'] && $percentageScore <= $grade['max_score']) {
                return $grade['label'];
            }
        }

        return null;
    }

    private function gradeSingleChoice(ExamAnswer $answer, Question $question): bool
    {
        $selected = $answer->selected_option_ids ?? [];
        if (count($selected) !== 1) {
            return false;
        }

        $correctOption = $question->options()->where('is_correct', true)->first();

        return $correctOption && $selected[0] === $correctOption->id;
    }
}
