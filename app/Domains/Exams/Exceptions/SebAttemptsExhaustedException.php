<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class SebAttemptsExhaustedException extends BaseDomainException
{
    protected $message = 'You have no attempts remaining for this exam.';

    protected int $httpStatus = 409;
}
