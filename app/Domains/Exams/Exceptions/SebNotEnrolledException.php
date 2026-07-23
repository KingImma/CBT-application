<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class SebNotEnrolledException extends BaseDomainException
{
    protected $message = 'You are not enrolled in the class or subject for this exam.';

    protected int $httpStatus = 403;
}
