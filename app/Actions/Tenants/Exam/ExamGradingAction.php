<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\GradingScale;
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
            $totalScore = $attempt->answers()->sum('marks_awarded') ?? 0;
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
                'objective_score' => $totalScore,
                'theory_score' => 0,
                'grade' => $grade,
            ]);

            $exam = $attempt->exam;
            $ungradedAttempts = $exam->attempts()
                ->whereIn('status', [
                    ExamAttemptStatus::Submitted->value,
                    ExamAttemptStatus::Timed_out->value,
                    ExamAttemptStatus::Grading->value,
                    ExamAttemptStatus::Disqualified->value,
                ])->exists();

            if (! $ungradedAttempts && $exam->status === ExamStatus::Grading) {
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
}
