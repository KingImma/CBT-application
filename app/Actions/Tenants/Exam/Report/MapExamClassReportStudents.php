<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam\Report;

use App\Data\Exam\Output\ExamClassReportStudentRowData;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\StudentProfile;
use Illuminate\Support\Collection;

class MapExamClassReportStudents
{
    /**
     * Map each student to a row DTO.
     *
     * @param  Collection<int, StudentProfile>  $students
     * @param  Collection<string, ExamAttempt>  $attemptsByStudentId
     * @return Collection<int, ExamClassReportStudentRowData>
     */
    public function execute(Exam $exam, Collection $students, Collection $attemptsByStudentId): Collection
    {
        return $students->map(function (StudentProfile $studentProfile) use ($exam, $attemptsByStudentId) {
            $user = $studentProfile->user;
            $attempt = $attemptsByStudentId->get($user->id);

            if ($attempt === null) {
                return new ExamClassReportStudentRowData(
                    student_id: $user->id,
                    student_name: $user->first_name.' '.$user->last_name,
                    score: null,
                    percentage: null,
                    grade: null,
                    result_status: 'not_attempted',
                    submitted_at: null,
                    completed_at: null,
                );
            }

            $resultStatus = $this->resolveResultStatus($attempt, $exam);
            $submittedAt = $attempt->submitted_at?->toIso8601String();

            return new ExamClassReportStudentRowData(
                student_id: $user->id,
                student_name: $user->first_name.' '.$user->last_name,
                score: $attempt->total_score !== null ? (float) $attempt->total_score : null,
                percentage: $attempt->percentage_score !== null ? (float) $attempt->percentage_score : null,
                grade: $attempt->grade,
                result_status: $resultStatus,
                submitted_at: $submittedAt,
                completed_at: $submittedAt,
            );
        })->values();
    }

    private function resolveResultStatus(ExamAttempt $attempt, Exam $exam): string
    {
        return match ($attempt->status) {
            ExamAttemptStatus::Graded => $this->determinePassFail($attempt, $exam),
            ExamAttemptStatus::Timed_out => 'timed_out',
            ExamAttemptStatus::Disqualified => 'disqualified',
            ExamAttemptStatus::Grading => 'grading',
            default => 'not_attempted',
        };
    }

    private function determinePassFail(ExamAttempt $attempt, Exam $exam): string
    {
        if ($attempt->total_score === null || $exam->pass_mark === null) {
            return 'grading';
        }

        return $attempt->total_score >= $exam->pass_mark ? 'passed' : 'failed';
    }
}
