<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Student;

use App\Exceptions\Domain\BaseDomainException;

class StudentCannotBeDeletedException extends BaseDomainException
{
    protected $message = 'Student cannot be deleted in their current state.';
}
