<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Support;

use App\Domains\Assessments\Exceptions\ScheduleSubjectOutOfRangeException;
use App\Domains\Assessments\Exceptions\ScheduleSubjectOverlapException;
use App\Domains\Assessments\Exceptions\ScheduleWindowNotSetException;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\SubjectSchedule;
use Closure;
use Illuminate\Support\Carbon;

final class ScheduleSubjectRules
{
    public static function canAssignWindow(?string $excludeScheduleSubjectId = null): Closure
    {
         // (a): assesment schedule must exist first
        throw_unless(
            $assesment->student_starts_at !== null && $assesment->student_ends_at !== null,
            new ScheduleWindoeNotSetException()
        );

        throw_unless(
            $startsAt->lt($endsAt),
            new ScheduleSubjectOutOfRangeException(
                    $assessment->student_starts_at->toDateTimeString(),
                    $assessment->student_ends_at->toDateTimeString(),
                )
        );

        // (b): Subject schedule must be within the assesment schedule
        throw_unless(
            $startsAt->gte($assesment->student_starts_at) && $endsAt->lte($assesment->student_ends_at),
            new ScheduleSubjectOutOfRangeException(
                    $assessment->student_starts_at->toDateTimeString(),
                    $assessment->student_ends_at->toDateTimeString(),
                )
        );

        // No overlapping subject schedules
        $conflict = SubjectSchedule::where('assesment_id', $assesment->id)
            ->when($excludeScheduleSubjectId, fn ($q) => $q->where('id', '!=', $excludeScheduleSubjectId) )
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->with('subject')
            ->first();
        
        throw_if(
            $conflict !== null,
            new ScheduleSubjectOverlapException(
                    $conflict->subject->name,
                    $conflict->starts_at->toDateTimeString(),
                    $conflict->ends_at->toDateTimeString(),
                )
        );
    }
}