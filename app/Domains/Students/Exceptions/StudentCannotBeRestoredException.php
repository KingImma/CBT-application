<?php

declare(strict_types=1);

namespace App\Domains\Students\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class StudentCannotBeRestoredException extends BaseDomainException
{
    protected $message = 'Student cannot be restored in their current state.';
}
