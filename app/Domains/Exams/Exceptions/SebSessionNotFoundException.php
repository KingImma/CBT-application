<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class SebSessionNotFoundException extends BaseDomainException
{
    protected $message = 'No pending SEB session found. Please restart the exam from the app.';

    protected int $httpStatus = 404;
}
