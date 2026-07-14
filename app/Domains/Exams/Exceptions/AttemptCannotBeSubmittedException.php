<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class AttemptCannotBeSubmittedException extends BaseDomainException
{
    protected $message = 'Exam attempt cannot be submitted in its current state.';
}
