<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\GradingScale;
use App\Services\Exam\GradeResolver;
use App\Services\Exam\ScoreCalculator;
use Illuminate\Support\Facades\DB;

class ExamGradingAction
{
    public function recomputeScore(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $totalScore = $attempt->answers()->sum('marks_awarded') ?? 0;
            $percentageScore = ScoreCalculator::percentage((float) $totalScore, (float) $attempt->exam->total_marks);

            $defaultScale = GradingScale::where('is_default', true)->first();
            $grade = GradeResolver::resolve($percentageScore, $defaultScale?->grades);

            $attempt->update([
                'total_score' => $totalScore,
                'percentage_score' => $percentageScore,
                'grade' => $grade,
            ]);

            return $attempt->fresh();
        });
    }
}
