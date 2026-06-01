<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Question;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasAnyRole(['admin', 'school_admin'])) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('teacher') || $user->hasAnyRole(['admin', 'school_admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'school_admin']);
    }

    public function createForClass(User $user, string $classLevelId): bool
    {
        return $user->teacherProfile?->class_level_id === $classLevelId
            || ClassArm::where('assigned_teacher_id', $user->id)
                ->where('class_level_id', $classLevelId)
                ->exists()
            || TeacherSubjectAssignment::where('user_id', $user->id)
                ->where('class_level_id', $classLevelId)
                ->exists();
    }

    public function view(User $user, Question $question): bool
    {
        return $user->id === $question->created_by
            || $this->isClassTeacher($user, $question)
            || $this->isSubjectTeacher($user, $question);
    }

    public function update(User $user, Question $question): bool
    {
        if ($user->id === $question->created_by) {
            return true;
        }

        if ($this->isSubjectTeacher($user, $question)) {
            return true;
        }

        if ($this->isClassTeacher($user, $question)) {
            $hasSubjectTeacher = TeacherSubjectAssignment::where('subject_id', $question->subject_id)
                ->where('class_level_id', $question->class_level_id)
                ->where('user_id', '!=', $user->id)
                ->exists();

            return ! $hasSubjectTeacher;
        }

        return false;
    }

    public function delete(User $user, Question $question): bool
    {
        return $this->update($user, $question);
    }

    private function isClassTeacher(User $user, Question $question): bool
    {
        // 1. In-memory check: Are they the overseer of this level?
        return $user->teacherProfile?->class_level_id === $question->class_level_id
        // 2. Database check: Do they manage a specific arm in this level?
            || ClassArm::where('assigned_teacher_id', $user->id)
                ->where('class_level_id', $question->class_level_id)
                ->exists();
    }

    private function isSubjectTeacher(User $user, Question $question): bool
    {
        return TeacherSubjectAssignment::where('user_id', $user->id)
            ->where('subject_id', $question->subject_id)
            ->where('class_level_id', $question->class_level_id)
            ->exists();
    }
}
