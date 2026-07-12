<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Student;

use App\Exceptions\Domain\BaseDomainException;

class StudentCannotBeRestoredException extends BaseDomainException
{
    protected $message = 'Student cannot be restored in their current state.';
}
