<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Report;

use App\Data\Exam\Output\ExamClassReportSummaryData;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\StudentProfile;
use Illuminate\Support\Collection;

class ComputeExamClassSummary
{
    /**
     * Compute summary metrics from loaded data.
     *
     * @param  Collection<int, StudentProfile>  $students
     * @param  Collection<string, ExamAttempt>  $attemptsByStudentId
     */
    public function execute(Exam $exam, ClassArm $arm, Collection $students, Collection $attemptsByStudentId): ExamClassReportSummaryData
    {
        $studentsInClass = $students->count();
        $studentsSat = $attemptsByStudentId->count();

        // Sitters = students with a completed attempt (skip grading status)
        $sitters = $attemptsByStudentId->filter(
            fn (ExamAttempt $attempt) => $attempt->status !== ExamAttemptStatus::Grading->value
        );

        $scores = $sitters->pluck('percentage_score')
            ->filter(fn ($val) => $val !== null)
            ->map(fn ($val) => (float) $val);

        $averageScore = $scores->isNotEmpty() ? round($scores->avg(), 2) : null;
        $highestScore = $scores->isNotEmpty() ? $scores->max() : null;
        $lowestScore = $scores->isNotEmpty() ? $scores->min() : null;

        // Pass/fail: use total_score >= exam.pass_mark.
        // Exclude grading and non-sitters.
        $passMark = $exam->pass_mark;
        $passCount = 0;
        $failCount = 0;

        foreach ($sitters as $attempt) {
            if ($attempt->total_score === null || $passMark === null) {
                continue;
            }
            if ($attempt->total_score >= $passMark) {
                $passCount++;
            } else {
                $failCount++;
            }
        }

        $completionStatus = match (true) {
            $studentsSat === 0 => 'none',
            $studentsSat < $studentsInClass => 'partial',
            default => 'complete',
        };

        $completionRate = $studentsInClass > 0
            ? round($studentsSat / $studentsInClass * 100, 2)
            : 0.0;

        return new ExamClassReportSummaryData(
            exam_id: $exam->id,
            exam_name: $exam->title,
            class_arm_id: $arm->id,
            class_arm_name: $arm->name,
            students_in_class: $studentsInClass,
            students_sat: $studentsSat,
            average_score: $averageScore,
            highest_score: $highestScore,
            lowest_score: $lowestScore,
            pass_count: $passCount > 0 || $failCount > 0 ? $passCount : null,
            fail_count: $passCount > 0 || $failCount > 0 ? $failCount : null,
            completion_status: $completionStatus,
            completion_rate: $completionRate,
            exam_status: $exam->status->value,
        );
    }
}
