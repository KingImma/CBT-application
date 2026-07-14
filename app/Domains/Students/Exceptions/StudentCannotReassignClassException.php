<?php

declare(strict_types=1);

namespace App\Domains\Students\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class StudentCannotReassignClassException extends BaseDomainException
{
    protected $message = 'Student cannot be reassigned to a different class in their current state.';
}
