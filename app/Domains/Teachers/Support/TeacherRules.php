<?php

declare(strict_types=1);

namespace App\Domains\Teachers\Support;

use App\Domains\Teachers\Exceptions\TeacherCannotBeDeletedException;
use App\Domains\Teachers\Exceptions\TeacherCannotBeRestoredException;
use App\Domains\Teachers\Exceptions\TeacherCannotBeRevokedException;
use App\Domains\Teachers\Exceptions\TeacherCannotBeUpdatedException;
use App\Models\Tenant\User;
use Closure;

final class TeacherRules
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
