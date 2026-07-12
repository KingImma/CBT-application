<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Teacher;

use App\Exceptions\Domain\BaseDomainException;

class TeacherCannotBeDeletedException extends BaseDomainException
{
    protected $message = 'Teacher cannot be deleted in their current state.';
}
