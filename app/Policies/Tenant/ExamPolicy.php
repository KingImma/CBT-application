<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\ExamStatus;
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
        if ($user->hasRole('school_admin')) {
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
        return $user->hasRole('teacher');
    }

    public function update(User $user, Exam $exam): bool
    {
        return $exam->isOwnedBy($user) && $exam->isDraft();
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $exam->isOwnedBy($user) && $exam->isDraft();
    }

    public function publish(User $user, Exam $exam): bool
    {
        return $exam->isOwnedBy($user) && ($exam->isDraft() || $exam->status === ExamStatus::Grading || $exam->status === ExamStatus::Completed);
    }

    public function submitForReview(User $user, Exam $exam): bool
    {
        return $exam->isDraft()
            && ($exam->isOwnedBy($user) || $this->isAssignedTeacher($user, $exam));
    }

    public function reject(User $user, Exam $exam): bool
    {
        return $exam->isOwnedBy($user) && $exam->isSubmitted();
    }

    public function activate(User $user, Exam $exam): bool
    {
        return $exam->isSubmitted();
    }

    public function lock(User $user, Exam $exam): bool
    {
        return $exam->canBeLocked();
    }

    public function startSession(User $user, Exam $exam): bool
    {
        return $exam->isOwnedBy($user) && $exam->isScheduled();
    }

    public function viewMonitoring(User $user, Exam $exam): bool
    {
        return $exam->isOwnedBy($user);
    }

    public function recall(User $user, Exam $exam): bool
    {
        return $exam->isOwnedBy($user) && $exam->isScheduled();
    }

    public function unlock(User $user, Exam $exam): bool
    {
        return $user->hasRole('school_admin') && $exam->status === ExamStatus::Locked;
    }

    public function emergencyRevert(User $user, Exam $exam): bool
    {
        return $user->hasRole('school_admin') && $exam->isGrading();
    }

    public function manageQuestions(User $user, Exam $exam): bool
    {
        return $exam->isDraft()
            && ($exam->isOwnedBy($user) || $this->isAssignedTeacher($user, $exam));
    }

    private function isAssignedTeacher(User $user, Exam $exam): bool
    {
        if ($this->isSubjectTeacher($user, $exam)) {
            return true;
        }

        if ($this->isClassTeacher($user, $exam)) {
            return ! TeacherSubjectAssignment::where('subject_id', $exam->subject_id)
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
}
