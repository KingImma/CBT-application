<?php

declare(strict_types=1);

namespace App\Domains\Teachers\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class TeacherCannotBeDeletedException extends BaseDomainException
{
    protected $message = 'Teacher cannot be deleted in their current state.';
}
