<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Teacher;

use App\Exceptions\Domain\BaseDomainException;

class TeacherCannotBeRevokedException extends BaseDomainException
{
    protected $message = 'Teacher cannot be revoked in their current state.';
}
