<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class ExamCannotBeSubmittedException extends BaseDomainException
{
    protected $message = 'Exam cannot be submitted for review in its current state.';
}
