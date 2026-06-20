<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Actions\Exam\CalculateScore;
use App\Actions\Exam\ResolveGrade;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\GradingScale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExamGradingAction
{
    public function execute(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(fn () => $this->performGrading($attempt));
    }

    private function performGrading(ExamAttempt $attempt): ExamAttempt
    {
        $exam = $attempt->exam;
        
        // Eager load questions to prevent N+1 queries inside the grading loop
        $answers = $attempt->answers()->with('question.options')->get();
        $examQuestions = $exam->examQuestions()->get()->keyBy('question_id');

        $runningTotal = 0.0;
        $maxTime = 0;

        foreach ($answers as $answer) {
            $marksAwarded = $this->gradeSingleAnswer($answer, $examQuestions);

            $runningTotal += $marksAwarded;
            $maxTime = max($maxTime, $answer->time_spent_seconds ?? 0);
        }

        return $this->finalizeAttempt($attempt, $runningTotal, $maxTime, (float) $exam->total_marks);
    }

    private function gradeSingleAnswer(ExamAnswer $answer, Collection $examQuestions): float
    {
        $selectedIds = $answer->selected_option_ids ?? [];
        $correctOption = $answer->question->options->firstWhere('is_correct', true);

        // Basic validation for single-choice questions
        $isCorrect = count($selectedIds) === 1 && $correctOption?->id === $selectedIds[0];

        $marksAwarded = 0.0;

        if ($isCorrect) {
            $examQuestion = $examQuestions->get($answer->question_id);
            $marksAwarded = (float) ($examQuestion?->getEffectiveMarks() ?? $answer->question->default_marks);
        }

        // updateQuietly prevents firing model events on every single answer update
        $answer->updateQuietly([
            'is_correct'    => $isCorrect,
            'marks_awarded' => $marksAwarded,
        ]);

        return $marksAwarded;
    }

    private function finalizeAttempt(ExamAttempt $attempt, float $runningTotal, int $maxTime, float $totalExamMarks): ExamAttempt
    {
        $percentageScore = CalculateScore::execute($runningTotal, $totalExamMarks);

        $defaultScale = GradingScale::where('is_default', true)->first();
        $grade = ResolveGrade::execute($percentageScore, $defaultScale?->grades);

        $attempt->update([
            'time_spent_seconds' => $maxTime ?: (int) now()->diffInSeconds($attempt->started_at),
            'total_score'        => $runningTotal,
            'percentage_score'   => $percentageScore,
            'grade'              => $grade,
        ]);

        return $attempt->fresh();
    }
}