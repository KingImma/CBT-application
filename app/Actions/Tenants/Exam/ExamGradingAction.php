<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Actions\Exam\CalculateScore;
use App\Actions\Exam\ResolveGrade;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\GradingScale;

class ExamGradingAction
{
    public function recomputeScore(ExamAttempt $attempt): ExamAttempt
    {
        $exam = $attempt->exam;

        $answers = ExamAnswer::with('question.options')
            ->where('attempt_id', $attempt->id)
            ->get();

        $examQuestions = ExamQuestion::where('exam_id', $exam->id)
            ->get()
            ->keyBy('question_id');

        $runningTotal = 0;
        $maxTime = 0;

        foreach ($answers as $answer) {
            $selected = $answer->selected_option_ids ?? [];
            $correctOption = $answer->question->options->firstWhere('is_correct', true);
            $isCorrect = count($selected) === 1 && $correctOption?->id === $selected[0];

            $marksAwarded = 0;
            if ($isCorrect) {
                $eq = $examQuestions->get($answer->question_id);
                $marksAwarded = $eq?->getEffectiveMarks() ?? $answer->question->default_marks;
            }

            $answer->updateQuietly([
                'is_correct' => $isCorrect,
                'marks_awarded' => $marksAwarded,
            ]);

            $runningTotal += $marksAwarded;
            $maxTime = max($maxTime, $answer->time_spent_seconds ?? 0);
        }

        $percentageScore = CalculateScore::execute($runningTotal, (float) $exam->total_marks);

        $defaultScale = GradingScale::where('is_default', true)->first();
        $grade = ResolveGrade::execute($percentageScore, $defaultScale?->grades);

        $attempt->time_spent_seconds = $maxTime ?: (int) now()->diffInSeconds($attempt->started_at);
        $attempt->total_score = $runningTotal;
        $attempt->percentage_score = $percentageScore;
        $attempt->grade = $grade;
        $attempt->save();

        return $attempt->fresh();
    }
}
