<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Student;

use App\Exceptions\Domain\BaseDomainException;

class StudentCannotBeRevokedException extends BaseDomainException
{
    protected $message = 'Student cannot be revoked in their current state.';
}
