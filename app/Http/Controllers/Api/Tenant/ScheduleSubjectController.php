<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Assessments\Data\Output\ScheduleSubjectData as ScheduleSubjectOutput;
use App\Domains\Assessments\Support\DailySubjectCap;
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
            ->map(fn ($slot) => $slot->starts_at->toDateString())
            ->unique()
            ->mapWithKeys(fn (string $date): array => [
                $date => ScheduleSubject::query()
                    ->whereHas('schedule', fn ($q) => $q->where('class_level_id', $schedule->class_level_id))
                    ->whereDate('starts_at', $date)
                    ->count(),
            ]);

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