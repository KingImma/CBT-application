<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

class SubmissionMarksExceedCapException extends SubmissionStateTransitionException
{
    protected $message = 'The total marks of the submission exceed the assessment cap.';
}
