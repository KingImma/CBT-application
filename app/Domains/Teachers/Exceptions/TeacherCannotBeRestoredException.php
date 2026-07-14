<?php

declare(strict_types=1);

namespace App\Domains\Teachers\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class TeacherCannotBeRestoredException extends BaseDomainException
{
    protected $message = 'Teacher cannot be restored in their current state.';
}
