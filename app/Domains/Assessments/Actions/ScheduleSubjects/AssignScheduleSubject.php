<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\ScheduleSubjects;

use App\Domains\Assessments\Data\Input\ScheduleSubjectData;
use App\Domains\Assessments\Support\ScheduleSubjectRules;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\ScheduleSubject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class AssignScheduleSubject
{
    public function execute(AssessmentSchedule $schedule, ScheduleSubjectData $dto): ScheduleSubject
    {
        return DB::transaction(function () use ($schedule, $dto): ScheduleSubject {
            $startsAt = Carbon::parse($dto->starts_at);
            $endsAt = Carbon::parse($dto->ends_at);

            ScheduleSubjectRules::canAssignWindow()($schedule, $startsAt, $endsAt);

            return $schedule->scheduleSubjects()->create([
                'subject_id' => $dto->subject_id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $dto->duration_minutes,
            ]);
        });
    }
}
