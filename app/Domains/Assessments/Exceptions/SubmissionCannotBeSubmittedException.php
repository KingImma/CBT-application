<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

class SubmissionCannotBeSubmittedException extends SubmissionStateTransitionException
{
    protected $message = 'Submission cannot be submitted for review in its current state.';
}
