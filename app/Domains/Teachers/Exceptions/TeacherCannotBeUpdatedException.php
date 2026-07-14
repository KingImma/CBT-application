<?php

declare(strict_types=1);

namespace App\Domains\Teachers\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class TeacherCannotBeUpdatedException extends BaseDomainException
{
    protected $message = 'Teacher cannot be updated in thier current state.';
}
