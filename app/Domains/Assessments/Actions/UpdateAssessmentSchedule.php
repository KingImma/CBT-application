<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Data\Input\UpdateScheduleData;
use App\Domains\Assessments\Exceptions\AssessmentStateTransitionException;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\ClassArm;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Optional;

final class UpdateAssessmentSchedule
{
    public function __construct() {}

    /**
     * Adjust the windows (and, while still draft, the class binding) of a
     * draft schedule. Once activated (or completed) the schedule is locked.
     * Existing subject slots must still fit inside a reshaped master window.
     */
    public function execute(AssessmentSchedule $schedule, UpdateScheduleData $dto): AssessmentSchedule
    {
        return DB::transaction(function () use ($schedule, $dto): AssessmentSchedule {
            throw_unless(
                $schedule->isDraft(),
                new AssessmentStateTransitionException(
                    'Only draft schedules can be edited.'
                )
            );

            // Class binding may move while draft; the arm must belong to the
            // level it sits under.
            if (! $dto->class_level_id instanceof Optional) {
                $schedule->class_level_id = $dto->class_level_id ?? $schedule->class_level_id;
                $schedule->class_arm_id = null;
            }

            if (! $dto->class_arm_id instanceof Optional) {
                throw_if(
                    $dto->class_arm_id !== null
                    && ! ClassArm::whereKey($dto->class_arm_id)
                        ->where('class_level_id', $schedule->class_level_id)
                        ->exists(),
                    ValidationException::withMessages([
                        'class_arm_id' => ['The selected class arm does not belong to the schedule\'s class level.'],
                    ])
                );

                $schedule->class_arm_id = $dto->class_arm_id;
            }

            $starts = $dto->assessment_starts instanceof Optional
                ? $schedule->assessment_starts
                : ($dto->assessment_starts !== null ? Carbon::parse($dto->assessment_starts) : null);

            $ends = $dto->assessment_ends instanceof Optional
                ? $schedule->assessment_ends
                : ($dto->assessment_ends !== null ? Carbon::parse($dto->assessment_ends) : null);

            throw_if(
                $starts !== null && $ends !== null && $starts->gte($ends),
                ValidationException::withMessages([
                    'assessment_ends' => ['The student end time must be after the student start time.'],
                ])
            );

            if ($starts !== null && $ends !== null) {
                $outside = $schedule->scheduleSubjects()
                    ->where(fn ($q) => $q->where('starts_at', '<', $starts)->orWhere('ends_at', '>', $ends))
                    ->count();

                throw_if(
                    $outside > 0,
                    ValidationException::withMessages([
                        'assessment_starts' => ["{$outside} subject slot(s) would fall outside the new exam window."],
                    ])
                );
            }

            if (! $dto->question_submission_ends instanceof Optional && $dto->question_submission_ends !== null) {
                $newEnds = Carbon::parse($dto->question_submission_ends);

                throw_unless(
                    $newEnds->isFuture(),
                    ValidationException::withMessages([
                        'question_submission_ends' => ['The question submission deadline must be in the future.'],
                    ])
                );

                $schedule->question_submission_ends = $newEnds;
            }

            $schedule->assessment_starts = $starts;
            $schedule->assessment_ends = $ends;
            $schedule->save();

            return $schedule->fresh();
        });
    }
}
