<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Exam;

use App\Exceptions\Domain\BaseDomainException;

class ExamCannotBeActivatedException extends BaseDomainException
{
    protected $message = 'Exam cannot be activated in its current state.';
}
