<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\Exam;
use App\Models\Tenant\GradingScale;
use Illuminate\Support\Facades\DB;

class RecomputeAttemptScoreAction
{
    public function execute(ExamAttempt $attempt): ExamAttempt
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

            // Lookup grade from GradingScale
            $grade = null;
            if ($exam->term && $exam->term->academicSession) {
                $grade = GradingScale::where('academic_session_id', $exam->term->academicSession->id)
                    ->where('min_percentage', '<=', $percentageScore)
                    ->where('max_percentage', '>=', $percentageScore)
                    ->value('grade');
            }

            $attempt->update([
                'total_score' => $totalScore,
                'percentage_score' => $percentageScore,
                'objective_score' => $objectiveScore,
                'theory_score' => $theoryScore,
                'grade' => $grade,
            ]);

            // Check if all attempts for this exam are graded, move to completed
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
}
