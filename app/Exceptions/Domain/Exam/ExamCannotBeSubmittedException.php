<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Exam;

use App\Exceptions\Domain\BaseDomainException;

class ExamCannotBeSubmittedException extends BaseDomainException
{
    protected $message = 'Exam cannot be submitted for review in its current state.';
}
