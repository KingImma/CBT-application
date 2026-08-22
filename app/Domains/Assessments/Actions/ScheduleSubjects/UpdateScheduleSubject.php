<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\ScheduleSubjects;

use App\Domains\Assessments\Data\Input\ScheduleSubjectData;
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

            // self-excluded from its own overlap check
            ScheduleSubjectRules::canAssignWindow($scheduleSubject->id)(
                $scheduleSubject->schedule,
                $startsAt,
                $endsAt,
            );

            $scheduleSubject->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'duration_minutes' => $dto->duration_minutes,
            ]);

            return $scheduleSubject->fresh();
        });
    }
}
