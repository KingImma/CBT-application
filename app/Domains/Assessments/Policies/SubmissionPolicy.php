<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Policies;

use App\Enums\RoleType;
use App\Models\Tenant\Submission;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * A submission is a teacher's paper inside an assessment. Teachers own the
 * authoring path (view/update/questions/submit) while it is still theirs;
 * admins own the review path (requestChanges/approve). There is deliberately no
 * before() admin override: every ability here carries its own status guard.
 */
class SubmissionPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Submission $submission): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value)
            || $submission->isOwnedBy($user);
    }

    public function update(User $user, Submission $submission): bool
    {
        return $submission->isOwnedBy($user) && $submission->isEditableByTeacher();
    }

    public function delete(User $user, Submission $submission): bool
    {
        return $submission->isOwnedBy($user) && $submission->isEditableByTeacher();
    }

    public function manageQuestions(User $user, Submission $submission): bool
    {
        return $submission->isOwnedBy($user) && $submission->isEditableByTeacher();
    }

    public function submitForReview(User $user, Submission $submission): bool
    {
        return $submission->isOwnedBy($user) && $submission->isEditableByTeacher();
    }

    public function requestChanges(User $user, Submission $submission): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value) && $submission->isSubmitted();
    }

    public function approve(User $user, Submission $submission): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value) && $submission->isSubmitted();
    }
}
