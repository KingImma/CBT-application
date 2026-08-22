<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

class ScheduleSubjectOverlapException extends AssessmentStateTransitionException
{
    public function __construct(string $conflictingSubject, string $start, string $end, ?\Throwable $previous = null)
    {
        parent::__construct("Time slot overlaps with '{$conflictingSubject}' ({$start} to {$end}) already scheduled under this class.", 422, $previous);
    }
}