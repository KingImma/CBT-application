<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Exam;

use App\Exceptions\Domain\BaseDomainException;

class ExamCannotBeCompletedException extends BaseDomainException
{
    protected $message = 'Exam cannot be completed in its current state.';
}
