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

/** Pure aggregation — reads only, no writes, no base primitive needed. */
final class ComputeExamClassSummary
{
    /**
     * @param  Collection<int, StudentProfile>  $students
     * @param  Collection<string, ExamAttempt>  $attemptsByStudentId
     */
    public function execute(Exam $exam, ClassArm $arm, Collection $students, Collection $attemptsByStudentId): ExamClassReportSummaryData
    {
        $studentsInClass = $students->count();
        $sitters = $attemptsByStudentId->filter(
            fn (ExamAttempt $a) => $a->status !== ExamAttemptStatus::Grading
        );

        $scores = $sitters->pluck('percentage_score')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
        $passMark = $exam->pass_mark;
        $passCount = 0;
        $failCount = 0;

        foreach ($sitters as $attempt) {
            if ($attempt->total_score === null || $passMark === null) {
                continue;
            }
            $attempt->total_score >= $passMark ? $passCount++ : $failCount++;
        }

        $studentsSat = $attemptsByStudentId->count();
        $completionStatus = match (true) {
            $studentsSat === 0 => 'none',
            $studentsSat < $studentsInClass => 'partial',
            default => 'complete',
        };

        return new ExamClassReportSummaryData(
            exam_id: $exam->id,
            exam_name: $exam->title,
            class_arm_id: $arm->id,
            class_arm_name: $arm->name,
            students_in_class: $studentsInClass,
            students_sat: $studentsSat,
            average_score: $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
            highest_score: $scores->isNotEmpty() ? $scores->max() : null,
            lowest_score: $scores->isNotEmpty() ? $scores->min() : null,
            pass_count: ($passCount + $failCount) > 0 ? $passCount : null,
            fail_count: ($passCount + $failCount) > 0 ? $failCount : null,
            completion_status: $completionStatus,
            completion_rate: $studentsInClass > 0 ? round($studentsSat / $studentsInClass * 100, 2) : 0.0,
            exam_status: $exam->status->value,
        );
    }
}
