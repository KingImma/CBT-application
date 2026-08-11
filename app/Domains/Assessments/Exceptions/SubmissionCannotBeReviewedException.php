<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

class SubmissionCannotBeReviewedException extends SubmissionStateTransitionException
{
    protected $message = 'Submission cannot be reviewed in its current state.';
}
