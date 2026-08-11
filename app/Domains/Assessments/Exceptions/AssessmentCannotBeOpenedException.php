<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

class AssessmentCannotBeOpenedException extends AssessmentStateTransitionException
{
    protected $message = 'Assessment cannot be opened in its current state.';
}
