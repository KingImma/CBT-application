<?php

declare(strict_types=1);

namespace App\Domains\Students\Support;

use App\Domains\Students\Exceptions\StudentCannotBeDeletedException;
use App\Domains\Students\Exceptions\StudentCannotBeRestoredException;
use App\Domains\Students\Exceptions\StudentCannotBeRevokedException;
use App\Domains\Students\Exceptions\StudentCannotBeUpdatedException;
use App\Domains\Students\Exceptions\StudentCannotReassignClassException;
use App\Models\Tenant\User;
use Closure;

final class StudentRules
{
    public static function canUpdate(): Closure
    {
        return function (User $student) {
            throw_if($student->trashed(), StudentCannotBeUpdatedException::class, 'Cannot update a revoked student.');
        };
    }

    public static function canRevoke(): Closure
    {
        return function (User $student) {
            throw_if($student->trashed(), StudentCannotBeRevokedException::class, 'Student is already revoked.');
        };
    }

    public static function canRestore(): Closure
    {
        return function (User $student) {
            throw_unless($student->trashed(), StudentCannotBeRestoredException::class, 'Student is not revoked.');
        };
    }

    public static function canDelete(): Closure
    {
        return function (User $student) {
            throw_unless($student->trashed(), StudentCannotBeDeletedException::class, 'Student must be revoked before permanent deletion.');
        };
    }

    public static function canReassignClass(): Closure
    {
        return function (User $student) {
            throw_if($student->trashed(), StudentCannotReassignClassException::class, 'Cannot reassign a revoked student.');
        };
    }
}
