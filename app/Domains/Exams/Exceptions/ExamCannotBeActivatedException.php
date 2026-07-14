<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class ExamCannotBeActivatedException extends BaseDomainException
{
    protected $message = 'Exam cannot be activated in its current state.';
}
