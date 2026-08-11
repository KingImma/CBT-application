<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

class AssessmentCannotBeCompletedException extends AssessmentStateTransitionException
{
    protected $message = 'Assessment cannot be completed in its current state.';
}
