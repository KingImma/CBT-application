<?php

declare(strict_types=1);

namespace App\Domains\Students\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class StudentCannotBeUpdatedException extends BaseDomainException
{
    protected $message = 'Student cannot be updated in their current state.';
}
