<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Policies;

use App\Enums\RoleType;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Assessments are admin-owned containers. Every *management* ability belongs to
 * the school admin; a teacher may only read a container whose class level they
 * are subject-assigned to (decision #1), so they can author a submission inside
 * it — see SubmissionPolicy. Lifecycle/timer legality is enforced by the domain
 * guards (409), not here — this policy is authz only.
 */
class AssessmentPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(RoleType::SchoolAdmin->value)) {
            return true;
        }

        return null;
    }

    /** The list itself is filtered by Assessment::scopeVisibleTo. */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleType::Teacher->value);
    }

    public function view(User $user, Assessment $assessment): bool
    {
        return $assessment->isOpenToTeacher($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }

    public function update(User $user, Assessment $assessment): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }

    public function delete(User $user, Assessment $assessment): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }

    public function open(User $user, Assessment $assessment): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }

    public function closeSubmissions(User $user, Assessment $assessment): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }

    public function reopen(User $user, Assessment $assessment): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }

    public function activate(User $user, Assessment $assessment): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }

    public function complete(User $user, Assessment $assessment): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }
}
