<?php

declare(strict_types=1);

namespace App\Domains\Students\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class StudentCannotBeRevokedException extends BaseDomainException
{
    protected $message = 'Student cannot be revoked in their current state.';
}
