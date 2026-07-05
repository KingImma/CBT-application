<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Teacher;

use App\Exceptions\Domain\Teacher\TeacherCannotBeDeletedException;
use App\Exceptions\Domain\Teacher\TeacherCannotBeRestoredException;
use App\Exceptions\Domain\Teacher\TeacherCannotBeRevokedException;
use App\Exceptions\Domain\Teacher\TeacherCannotBeUpdatedException;
use App\Models\Tenant\User;
use Closure;

final class TeacherGuards
{
    public static function canUpdate(): Closure
    {
        return function (User $teacher) {
            throw_if($teacher->trashed(), TeacherCannotBeUpdatedException::class, 'Cannot update a revoked teacher.');
        };
    }

    public static function canRevoke(): Closure
    {
        return function (User $teacher) {
            throw_if($teacher->trashed(), TeacherCannotBeRevokedException::class, 'Teacher is already revoked.');
        };
    }

    public static function canRestore(): Closure
    {
        return function (User $teacher) {
            throw_unless($teacher->trashed(), TeacherCannotBeRestoredException::class, 'Teacher is not revoked.');
        };
    }

    public static function canDelete(): Closure
    {
        return function (User $teacher) {
            throw_unless($teacher->trashed(), TeacherCannotBeDeletedException::class, 'Teacher must be revoked before permanent deletion.');
        };
    }
}
