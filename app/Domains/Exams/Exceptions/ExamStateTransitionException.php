<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class ExamStateTransitionException extends BaseDomainException
{
    protected $message = 'Invalid exam state transition.';
}
