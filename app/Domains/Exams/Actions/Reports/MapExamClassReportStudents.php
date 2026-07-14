<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Reports;

use App\Domains\Exams\Data\Output\ExamClassReportStudentRowData;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\StudentProfile;
use Illuminate\Support\Collection;

final class MapExamClassReportStudents
{
    /**
     * @param  Collection<int, StudentProfile>  $students
     * @param  Collection<string, ExamAttempt>  $attemptsByStudentId
     * @return Collection<int, ExamClassReportStudentRowData>
     */
    public function execute(Exam $exam, Collection $students, Collection $attemptsByStudentId): Collection
    {
        return $students->map(function (StudentProfile $profile) use ($exam, $attemptsByStudentId) {
            $user = $profile->user;
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

            return new ExamClassReportStudentRowData(
                student_id: $user->id,
                student_name: $user->first_name.' '.$user->last_name,
                score: $attempt->total_score !== null ? (float) $attempt->total_score : null,
                percentage: $attempt->percentage_score !== null ? (float) $attempt->percentage_score : null,
                grade: $attempt->grade,
                result_status: $this->resolveStatus($attempt, $exam),
                submitted_at: $attempt->submitted_at?->toIso8601String(),
                completed_at: $attempt->submitted_at?->toIso8601String(),
            );
        })->values();
    }

    private function resolveStatus(ExamAttempt $attempt, Exam $exam): string
    {
        return match ($attempt->status) {
            ExamAttemptStatus::Graded => $this->passOrFail($attempt, $exam),
            ExamAttemptStatus::Timed_out => 'timed_out',
            ExamAttemptStatus::Disqualified => 'disqualified',
            ExamAttemptStatus::Grading => 'grading',
            default => 'not_attempted',
        };
    }

    private function passOrFail(ExamAttempt $attempt, Exam $exam): string
    {
        if ($attempt->total_score === null || $exam->pass_mark === null) {
            return 'grading';
        }

        return $attempt->total_score >= $exam->pass_mark ? 'passed' : 'failed';
    }
}
