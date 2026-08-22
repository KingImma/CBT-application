<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

class ScheduleSubjectOutOfRangeException extends AssessmentStateTransitionException
{
    public function __construct(string $rangeStart, string $rangeEnd, ?\Throwable $previous = null)
    {
        parent::__construct("Subject window must fall within the schedule's exam period ({$rangeStart} to {$rangeEnd}).", 422, $previous);
    }
}