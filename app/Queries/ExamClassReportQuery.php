<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use RuntimeException;

/** Read-only query — loads students + latest graded attempt per student in one pass. */
final class ExamClassReportQuery
{
    public function execute(ClassArm $arm, Exam $exam): object
    {
        throw_unless(
            $exam->class_level_id === $arm->class_level_id,
            new RuntimeException('Exam does not belong to the same class level as the arm.')
        );

        throw_if(
            $exam->class_arm_id !== null && $exam->class_arm_id !== $arm->id,
            new RuntimeException('Exam is scoped to a different class arm.')
        );

        $students = $arm->students()->with('user')->get();

        if ($students->isEmpty()) {
            return (object) ['students' => collect(), 'attemptsByStudentId' => collect()];
        }

        $studentIds = $students->pluck('user_id');

        // DISTINCT ON (student_id) → latest completed attempt per student, single query
        $sub = ExamAttempt::query()
            ->selectRaw('DISTINCT ON (student_id) exam_attempts.*')
            ->where('exam_id', $exam->id)
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', [
                ExamAttemptStatus::Graded,
                ExamAttemptStatus::Timed_out,
                ExamAttemptStatus::Disqualified,
            ])
            ->orderBy('student_id')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');

        $attempts = ExamAttempt::fromSub($sub, 'latest_attempts')->get()->keyBy('student_id');

        return (object) ['students' => $students, 'attemptsByStudentId' => $attempts];
    }
}
