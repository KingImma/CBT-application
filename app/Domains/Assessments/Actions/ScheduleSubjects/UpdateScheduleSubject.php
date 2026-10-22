<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\ScheduleSubjects;

use App\Domains\Assessments\Data\Input\ScheduleSubjectData;
use App\Domains\Assessments\Exceptions\AssessmentStateTransitionException;
use App\Domains\Assessments\Support\ScheduleSubjectRules;
use App\Models\Tenant\ScheduleSubject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class UpdateScheduleSubject
{
    public function execute(ScheduleSubject $scheduleSubject, ScheduleSubjectData $dto): ScheduleSubject
    {
        return DB::transaction(function () use ($scheduleSubject, $dto): ScheduleSubject {
            $startsAt = Carbon::parse($dto->starts_at);
            $endsAt = Carbon::parse($dto->ends_at);

            $schedule = $scheduleSubject->schedule;
            
            throw_unless(
                $schedule->isDraft(),
                new AssessmentStateTransitionException(
                    'Subject slots can only be changed while the schedule is still a draft.'
                )
            );

            // self-excluded from its own overlap + cap checks
            ScheduleSubjectRules::canAssignWindow($scheduleSubject->id)($schedule, $startsAt, $endsAt);
            ScheduleSubjectRules::canAssignWithinDailyCap($scheduleSubject->id)($schedule, $startsAt);

            $scheduleSubject->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $dto->duration_minutes,
            ]);

            return $scheduleSubject->fresh();
        });
    }
}
