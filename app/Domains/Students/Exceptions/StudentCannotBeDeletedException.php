<?php

declare(strict_types=1);

namespace App\Domains\Students\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class StudentCannotBeDeletedException extends BaseDomainException
{
    protected $message = 'Student cannot be deleted in their current state.';
}
