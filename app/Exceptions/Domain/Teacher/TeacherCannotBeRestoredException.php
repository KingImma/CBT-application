<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Teacher;

use App\Exceptions\Domain\BaseDomainException;

class TeacherCannotBeRestoredException extends BaseDomainException
{
    protected $message = 'Teacher cannot be restored in their current state.';
}
