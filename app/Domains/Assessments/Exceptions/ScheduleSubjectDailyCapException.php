<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Exceptions;

class ScheduleSubjectDailyCapException extends AssessmentStateTransitionException
{
    public function __construct(string $classLevel, int $cap, string $date, ?\Throwable $previous = null)
    {
        parent::__construct(
            "{$classLevel} cannot be scheduled for more than {$cap} subject(s) on {$date}.",
            422,
            $previous,
        );
    }
}