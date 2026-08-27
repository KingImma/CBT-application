<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

class AssessmentCannotBePublishedException extends AssessmentStateTransitionException
{
    protected $message = 'Assessment cannot be published in its current state.';
}
