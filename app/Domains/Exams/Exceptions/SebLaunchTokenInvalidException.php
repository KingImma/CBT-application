<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class SebLaunchTokenInvalidException extends BaseDomainException
{
    protected $message = 'This launch link is invalid or has expired. Please restart the exam from the app.';

    protected int $httpStatus = 401;
}
