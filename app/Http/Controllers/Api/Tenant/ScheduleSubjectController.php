<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Assessments\Actions\ScheduleSubjects\AssignScheduleSubject;
use App\Domains\Assessments\Actions\ScheduleSubjects\RemoveScheduleSubject;
use App\Domains\Assessments\Actions\ScheduleSubjects\UpdateScheduleSubject;
use App\Domains\Assessments\Data\Input\ScheduleSubjectData;
use App\Domains\Assessments\Data\Output\ScheduleSubjectData as ScheduleSubjectOutput;
use App\Http\Controllers\Controller;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\ScheduleSubject;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Assessment Schedule — Subject Calendar
 * Per-subject exam windows nested inside an AssessmentSchedule's master
 * student window (assessment_starts / assessment_ends).
 */
class ScheduleSubjectController extends Controller
{
    public function __construct(
        private AssignScheduleSubject $assign,
        private UpdateScheduleSubject $update,
        private RemoveScheduleSubject $remove,
    ) {}

    /**
     * List all subject slots under a schedule — drives the calendar view.
     *
     * @urlParam schedule string required The assessment-schedule UUID.
     */
    public function index(AssessmentSchedule $schedule): JsonResponse
    {
        Gate::authorize('view', $schedule);

        $slots = $schedule->scheduleSubjects()
            ->with('subject:id,name,code')
            ->orderBy('starts_at')
            ->get();

        return ApiResponse::success(
            ScheduleSubjectOutput::collect($slots),
            'Schedule subjects retrieved successfully.'
        );
    }

    /**
     * Place a subject into the calendar. Rejects if outside the master window,
     * or overlapping another subject in this schedule.
     */
    public function store(ScheduleSubjectData $data, AssessmentSchedule $schedule): JsonResponse
    {
        Gate::authorize('manage', $schedule);

        $slot = $this->assign->execute($schedule, $data);

        return ApiResponse::created(
            ScheduleSubjectOutput::from($slot->load('subject:id,name,code')),
            'Subject scheduled.'
        );
    }

    /**
     * Move/resize a subject's slot. Same bounds + overlap checks apply,
     * excluding itself from the overlap comparison.
     */
    public function update(ScheduleSubjectData $data, AssessmentSchedule $schedule, ScheduleSubject $scheduleSubject): JsonResponse
    {
        Gate::authorize('manage', $schedule);

        abort_unless($scheduleSubject->assessment_schedule_id === $schedule->id, 404);

        $slot = $this->update->execute($scheduleSubject, $data);

        return ApiResponse::success(
            ScheduleSubjectOutput::from($slot->load('subject:id,name,code')),
            'Subject slot updated.'
        );
    }

    /**
     * Remove a subject slot. Blocked once the schedule is activated.
     */
    public function destroy(AssessmentSchedule $schedule, ScheduleSubject $scheduleSubject): JsonResponse
    {
        Gate::authorize('manage', $schedule);

        abort_unless($scheduleSubject->assessment_schedule_id === $schedule->id, 404);

        $this->remove->execute($scheduleSubject);

        return ApiResponse::message('Subject slot removed.');
    }
}
