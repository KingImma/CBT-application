<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class ExamCannotBeCompletedException extends BaseDomainException
{
    protected $message = 'Exam cannot be completed in its current state.';
}
