<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Student;

use App\Exceptions\Domain\BaseDomainException;

class StudentCannotReassignClassException extends BaseDomainException
{
    protected $message = 'Student cannot be reassigned to a different class in their current state.';
}
