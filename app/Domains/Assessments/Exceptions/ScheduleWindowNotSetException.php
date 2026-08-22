<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

class ScheduleWindowNotSetException extends AssessmentStateTransitionException
{
    public function __construct(string $rangeStart = 'not set', string $rangeEnd = 'not set', ?\Throwable $previous = null)
    {
        parent::__construct("Subject window must fall within the schedule's exam period ({$rangeStart} to {$rangeEnd}).", 422, $previous);
    }
}
