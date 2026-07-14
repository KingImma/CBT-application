<?php

declare(strict_types=1);

namespace App\Domains\Exams\Policies;

use App\Enums\ExamStatus;
use App\Enums\RoleType;
use App\Models\Tenant\Exam;
use App\Models\Tenant\User;
use App\Domains\Teachers\Support\TeacherClassAccess;
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
        if (
            ! $user->hasRole(RoleType::SchoolAdmin->value) &&
            ! $exam->isOwnedBy($user)
        ) {
            return false;
        }

        return $exam->status !== ExamStatus::Active &&
            $exam->status !== ExamStatus::Published &&
            $exam->completed_attempts === 0;
    }

    public function submitForReview(User $user, Exam $exam): bool
    {
        return $exam->isDraft() &&
            ($exam->isOwnedBy($user) ||
                TeacherClassAccess::isTeacherAssignedToExam($user, $exam));
    }

    public function activate(User $user, Exam $exam): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value) &&
            $exam->isSubmitted();
    }

    public function manageQuestions(User $user, Exam $exam): bool
    {
        return $exam->isDraft() &&
            ($exam->isOwnedBy($user) ||
                TeacherClassAccess::isTeacherAssignedToExam($user, $exam));
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

    public function forceComplete(User $user, Exam $exam): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value) &&
            $exam->status === ExamStatus::Active;
    }
}
