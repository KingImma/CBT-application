<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Policies;

use App\Domains\Teachers\Support\TeacherClassAccess;
use App\Enums\RoleType;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\Exam;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ClassArmPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(RoleType::SchoolAdmin->value)) {
            return true;
        }

        return null;
    }

    public function viewExamReport(User $user, ClassArm $classArm, Exam $exam): bool
    {
        return TeacherClassAccess::canViewExamForClass($user, $exam, $classArm);
    }
}
