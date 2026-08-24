<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Support;

use App\Domains\Assessments\Exceptions\ScheduleSubjectOutOfRangeException;
use App\Domains\Assessments\Exceptions\ScheduleSubjectOverlapException;
use App\Domains\Assessments\Exceptions\ScheduleWindowNotSetException;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\ScheduleSubject;
use Closure;
use Illuminate\Support\Carbon;

final class ScheduleSubjectRules
{
    /**
     * A subject slot must sit inside the schedule's master student window and
     * must not overlap any other slot on the same schedule.
     */
    public static function canAssignWindow(?string $excludeScheduleSubjectId = null): Closure
    {
        return function (
            AssessmentSchedule $schedule,
            Carbon $startsAt,
            Carbon $endsAt,
        ) use ($excludeScheduleSubjectId): void {
            throw_unless(
                $schedule->masterWindowIsSet(),
                new ScheduleWindowNotSetException
            );

            throw_unless(
                $startsAt->lt($endsAt),
                new ScheduleSubjectOutOfRangeException(
                    $schedule->assessment_starts->toDateTimeString(),
                    $schedule->assessment_ends->toDateTimeString(),
                )
            );

            // (a) within the schedule's master window
            throw_unless(
                $startsAt->gte($schedule->assessment_starts) &&
                $endsAt->lte($schedule->assessment_ends),
                new ScheduleSubjectOutOfRangeException(
                    $schedule->assessment_starts->toDateTimeString(),
                    $schedule->assessment_ends->toDateTimeString(),
                )
            );

            // (b) no overlapping slots on this schedule
            $conflict = ScheduleSubject::where('assessment_schedule_id', $schedule->id)
                ->when($excludeScheduleSubjectId, fn ($q) => $q->where('id', '!=', $excludeScheduleSubjectId))
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->with('subject')
                ->first();

            if ($conflict !== null) {
                throw new ScheduleSubjectOverlapException(
                    $conflict->subject->name,
                    $conflict->starts_at->toDateTimeString(),
                    $conflict->ends_at->toDateTimeString(),
                );
            }
        };
    }
}
