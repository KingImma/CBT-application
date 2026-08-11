<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class SubmissionStateTransitionException extends BaseDomainException
{
    protected $message = 'Invalid submission state transition.';

    protected int $httpStatus = 409;
}
