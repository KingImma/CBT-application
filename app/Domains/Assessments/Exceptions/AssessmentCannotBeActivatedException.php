<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

class AssessmentCannotBeActivatedException extends AssessmentStateTransitionException
{
    protected $message = 'Assessment cannot be activated in its current state.';
}
