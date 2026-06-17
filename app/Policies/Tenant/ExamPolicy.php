<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\RoleType;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ExamPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(RoleType::SchoolAdmin->value)) {
            return true;
        }

        return null;
    }

    public function view(User $user, Exam $exam): bool
    {
        return $exam->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleType::Teacher->value);
    }

    public function update(User $user, Exam $exam): bool
    {
        return $exam->isOwnedBy($user) && $exam->isDraft();
    }

    public function delete(User $user, Exam $exam): bool
    {
        if (! $user->hasRole(RoleType::SchoolAdmin->value) && ! $exam->isOwnedBy($user)) {
            return false;
        }

        return $exam->status !== ExamStatus::Active
            && $exam->status !== ExamStatus::Published
            && $exam->completed_attempts === 0;
    }

    public function submitForReview(User $user, Exam $exam): bool
    {
        return $exam->isDraft() &&
            ($exam->isOwnedBy($user) || $this->isAssignedTeacher($user, $exam));
    }

    public function activate(User $user, Exam $exam): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value) && $exam->isSubmitted();
    }

    public function manageQuestions(User $user, Exam $exam): bool
    {
        return $exam->isDraft() &&
            ($exam->isOwnedBy($user) || $this->isAssignedTeacher($user, $exam));
    }

    public function publish(User $user, Exam $exam): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }

    public function publishResults(User $user, Exam $exam): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }

    public function unpublishResults(User $user, Exam $exam): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }

    private function isAssignedTeacher(User $user, Exam $exam): bool
    {
        if ($this->isSubjectTeacher($user, $exam)) {
            return true;
        }

        if ($this->isClassTeacher($user, $exam)) {
            return ! TeacherSubjectAssignment::where(
                'subject_id',
                $exam->subject_id,
            )
                ->where('class_level_id', $exam->class_level_id)
                ->where('user_id', '!=', $user->id)
                ->exists();
        }

        return false;
    }

    private function isSubjectTeacher(User $user, Exam $exam): bool
    {
        return TeacherSubjectAssignment::where('user_id', $user->id)
            ->where('subject_id', $exam->subject_id)
            ->where('class_level_id', $exam->class_level_id)
            ->exists();
    }

    private function isClassTeacher(User $user, Exam $exam): bool
    {
        return ClassArm::where('assigned_teacher_id', $user->id)
            ->where('class_level_id', $exam->class_level_id)
            ->exists();
    }

    public function forceComplete(User $user, Exam $exam): bool
    {
        // Only school_admin, handled by before() hook already
        // but being explicit for clarity
        return $user->hasRole(RoleType::SchoolAdmin->value) && $exam->status === ExamStatus::Active;
    }
}
