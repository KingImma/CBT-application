<?php

declare (strict_types=1);

namespace App\Domains\Exams\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class SebExamNotLiveException extends BaseDomainException
{
    protected $message = 'This exam is not currently live or is outside its scheduled window.';

    protected int $httpStatus = 409;
}
