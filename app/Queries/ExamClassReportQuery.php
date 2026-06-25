<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;

class ExamClassReportQuery
{
    /**
     * Execute the query to load students and their latest completed attempts.
     *
     * @return object{exam: Exam, classArm: ClassArm, students: Collection<int, User>, attemptsByStudentId: Collection<string, ExamAttempt>}
     */
    public function execute(ClassArm $arm, Exam $exam): object
    {
        // Assert exam/class compatibility
        if ($exam->class_level_id !== $arm->class_level_id) {
            throw new \RuntimeException('Exam does not belong to the same class level as the arm.');
        }

        if ($exam->class_arm_id !== null && $exam->class_arm_id !== $arm->id) {
            throw new \RuntimeException('Exam is scoped to a different class arm.');
        }

        // Load all active students in the arm (eager-load user for name)
        $students = $arm->students()
            ->with('user')
            ->get();

        if ($students->isEmpty()) {
            return (object) [
                'exam' => $exam,
                'classArm' => $arm,
                'students' => collect(),
                'attemptsByStudentId' => collect(),
            ];
        }

        $studentIds = $students->pluck('user_id');

        // Load the latest completed attempt per student in a single query
        // Using a window function approach: rank by submitted_at desc, id desc per student
        $subQuery = ExamAttempt::query()
            ->selectRaw('DISTINCT ON (student_id) exam_attempts.*')
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', [
                ExamAttemptStatus::Graded,
                ExamAttemptStatus::Timed_out,
                ExamAttemptStatus::Disqualified,
            ])
            ->orderBy('student_id')
            ->orderBy('submitted_at', 'desc')
            ->orderBy('id', 'desc');

        // Build a collection keyed by student_id for easy lookup
        $attempts = ExamAttempt::query()
            ->fromSub($subQuery, 'latest_attempts')
            ->get()
            ->keyBy('student_id');

        return (object) [
            'exam' => $exam,
            'classArm' => $arm,
            'students' => $students,
            'attemptsByStudentId' => $attempts,
        ];
    }
}
