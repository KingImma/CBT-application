<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class AssessmentStateTransitionException extends BaseDomainException
{
    protected $message = 'Invalid assessment state transition.';

    protected int $httpStatus = 409;
}
