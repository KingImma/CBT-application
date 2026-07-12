<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Student;

use App\Exceptions\Domain\BaseDomainException;

class StudentCannotBeUpdatedException extends BaseDomainException
{
    protected $message = 'Student cannot be updated in their current state.';
}
