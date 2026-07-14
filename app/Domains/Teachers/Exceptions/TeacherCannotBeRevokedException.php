<?php

declare(strict_types=1);

namespace App\Domains\Teachers\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class TeacherCannotBeRevokedException extends BaseDomainException
{
    protected $message = 'Teacher cannot be revoked in their current state.';
}
