<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Assessments\Data\Output\ScheduleSubjectData as ScheduleSubjectOutput;
use App\Domains\Assessments\Support\DailySubjectCap;
use App\Domains\Assessments\Support\SchoolDay;
use App\Http\Controllers\Controller;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\ScheduleSubject;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Assessment Schedule — Subject Calendar
 *
 * Per-subject exam windows nested inside an AssessmentSchedule's master
 * student window. Read-only: teachers own slot writes via their submissions.
 */
class ScheduleSubjectController extends Controller
{
    public function index(AssessmentSchedule $schedule): JsonResponse
    {
        Gate::authorize('view', $schedule);

        $slots = $schedule->scheduleSubjects()
            ->with('subject:id,name,code')
            ->orderBy('starts_at')
            ->get();

        $dailyUsage = $slots
            ->map(fn (ScheduleSubject $slot) => SchoolDay::of($slot->starts_at))
            ->unique(fn ($day) => $day->toDateString())
            ->mapWithKeys(function ($day) use ($schedule): array {
                [$dayStartUtc, $dayEndUtc] = SchoolDay::bounds($day);

                return [
                    $day->toDateString() => ScheduleSubject::query()
                        ->whereHas('schedule', function ($q) use ($schedule) {
                            if ($schedule->class_arm_id !== null) {
                                $q->where('class_arm_id', $schedule->class_arm_id);
                            } else {
                                $q->where('class_level_id', $schedule->class_level_id);
                            }
                        })
                        ->where('starts_at', '>=', $dayStartUtc)
                        ->where('starts_at', '<', $dayEndUtc)
                        ->count(),
                ];
            });

        return ApiResponse::success(
            ScheduleSubjectOutput::collect($slots),
            'Schedule subjects retrieved successfully.',
            meta: [
                'daily_cap' => DailySubjectCap::limit(),
                'daily_usage' => $dailyUsage,
            ],
        );
    }
}