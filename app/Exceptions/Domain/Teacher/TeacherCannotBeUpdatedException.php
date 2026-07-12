<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Teacher;

use App\Exceptions\Domain\BaseDomainException;

class TeacherCannotBeUpdatedException extends BaseDomainException
{
    protected $message = 'Teacher cannot be updated in thier current state.';
}
