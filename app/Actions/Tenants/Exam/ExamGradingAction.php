<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\GradingScale;
use Illuminate\Support\Facades\DB;

class ExamGradingAction
{
    public function recomputeScore(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $totalScore = $attempt->answers()->sum('marks_awarded') ?? 0;
            $percentageScore = $attempt->exam->total_marks > 0
                ? ($totalScore / $attempt->exam->total_marks) * 100
                : 0;

            $grade = null;
            $defaultScale = GradingScale::where('is_default', true)->first();
            if ($defaultScale) {
                foreach ($defaultScale->grades as $gradeEntry) {
                    if ($percentageScore >= $gradeEntry['min_score'] && $percentageScore <= $gradeEntry['max_score']) {
                        $grade = $gradeEntry['label'];
                        break;
                    }
                }
            }

            $attempt->update([
                'total_score' => $totalScore,
                'percentage_score' => $percentageScore,
                'grade' => $grade,
            ]);

            return $attempt->fresh();
        });
    }
}
