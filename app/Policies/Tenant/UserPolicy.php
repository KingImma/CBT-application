<?php

declare(strict_types=1);

namespace App\Policies\Tenant;

use App\Enums\RoleType;
use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole(RoleType::SchoolAdmin->value)) {
            return true;
        }

        return null;
    }

    public function viewTeacher(User $user, User $teacher): bool
    {
        return $user->id === $teacher->id;
    }

    public function createTeacher(User $user): bool
    {
        return $user->hasRole(RoleType::SchoolAdmin->value);
    }

    public function updateTeacher(User $user, User $teacher): bool
    {
        return $user->id === $teacher->id;
    }

    public function deleteTeacher(User $user, User $teacher): bool
    {
        return false;
    }

    public function revokeTeacher(User $user, User $teacher): bool
    {
        return $teacher->is_active && ! $teacher->trashed();
    }

    public function restoreTeacher(User $user, User $teacher): bool
    {
        return $teacher->trashed();
    }

    public function importTeachers(User $user): bool
    {
        return true;
    }

    public function viewStudent(User $user, User $student): bool
    {
        return true;
    }

    public function createStudent(User $user): bool
    {
        return true;
    }

    public function updateStudent(User $user, User $student): bool
    {
        return true;
    }

    public function deleteStudent(User $user, User $student): bool
    {
        return false;
    }

    public function revokeStudent(User $user, User $student): bool
    {
        return $student->is_active && ! $student->trashed();
    }

    public function restoreStudent(User $user, User $student): bool
    {
        return $student->trashed();
    }

    public function reassignClass(User $user, User $student): bool
    {
        return true;
    }

    public function bulkResetPasswords(User $user): bool
    {
        return true;
    }

    public function importStudents(User $user): bool
    {
        return true;
    }
}
