<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Policies;

use App\Enums\RoleType;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Assessment schedules are admin-owned occurrences. A teacher may read a
 * schedule whose parent assessment class level they are subject-assigned to,
 * so they can author a paper inside its open question window. Lifecycle and
 * window legality is enforced by the domain guards (409), not here.
 */
class AssessmentSchedulePolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(RoleType::SchoolAdmin->value)) {
            return true;
        }

        return null;
    }

    public function view(User $user, AssessmentSchedule $schedule): bool
    {
        return $schedule->assessment->isOpenToTeacher($user);
    }

    public function manage(User $user, AssessmentSchedule $schedule): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }
}
