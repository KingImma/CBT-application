<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\User;

final class TeacherClassAccess
{
    /**
     * Determine if a teacher can view a class arm.
     */
    public static function canViewClass(User $teacher, ClassArm $arm): bool
    {
        // Teacher is the assigned class teacher (homeroom)
        if ($arm->assigned_teacher_id === $teacher->id) {
            return true;
        }

        // Teacher is a subject teacher for this level/arm
        if (
            TeacherSubjectAssignment::where('user_id', $teacher->id)
                ->where('class_level_id', $arm->class_level_id)
                ->where(
                    fn ($query) => $query
                        ->whereNull('class_arm_id')
                        ->orWhere('class_arm_id', $arm->id),
                )
                ->exists()
        ) {
            return true;
        }

        // Teacher is a level overseer
        if (
            $teacher->teacherProfile?->class_level_id === $arm->class_level_id
        ) {
            return true;
        }

        return false;
    }

    /**
     * Determine if a teacher can view a specific exam for a class arm.
     *
     * Validates the exam targets the arm's level (and arm, if scoped),
     * then applies subject-teacher precedence followed by broader checks.
     */
    public static function canViewExamForClass(
        User $teacher,
        Exam $exam,
        ClassArm $arm,
    ): bool {
        // The exam must target the arm's class level
        if ($exam->class_level_id !== $arm->class_level_id) {
            return false;
        }

        // If the exam is scoped to a specific arm, it must match
        if ($exam->class_arm_id !== null && $exam->class_arm_id !== $arm->id) {
            return false;
        }

        // Subject teacher (specific subject) — always allowed
        if (self::isSubjectTeacher($teacher, $exam)) {
            return true;
        }

        // Level overseer — always allowed
        if (
            $teacher->teacherProfile?->class_level_id === $arm->class_level_id
        ) {
            return true;
        }

        // Class teacher — allowed only if no other subject teacher is
        // assigned to this subject + level (original ExamPolicy precedence)
        if ($arm->assigned_teacher_id === $teacher->id) {
            return ! TeacherSubjectAssignment::where(
                'subject_id',
                $exam->subject_id,
            )
                ->where('class_level_id', $exam->class_level_id)
                ->where('user_id', '!=', $teacher->id)
                ->exists();
        }

        return false;
    }

    /**
     * Determine if a teacher is assigned to an exam (without requiring a specific ClassArm).
     *
     * This replicates the original ExamPolicy::isAssignedTeacher logic:
     * - Subject teachers always have access to their subject exam.
     * - Class teachers have access only if no competing subject teacher is assigned.
     */
    public static function isTeacherAssignedToExam(
        User $teacher,
        Exam $exam,
    ): bool {
        if (self::isSubjectTeacher($teacher, $exam)) {
            return true;
        }

        if (
            ClassArm::where('assigned_teacher_id', $teacher->id)
                ->where('class_level_id', $exam->class_level_id)
                ->exists()
        ) {
            return ! TeacherSubjectAssignment::where(
                'subject_id',
                $exam->subject_id,
            )
                ->where('class_level_id', $exam->class_level_id)
                ->where('user_id', '!=', $teacher->id)
                ->exists();
        }

        return false;
    }

    private static function isSubjectTeacher(User $teacher, Exam $exam): bool
    {
        return TeacherSubjectAssignment::where('user_id', $teacher->id)
            ->where('subject_id', $exam->subject_id)
            ->where('class_level_id', $exam->class_level_id)
            ->exists();
    }
}
