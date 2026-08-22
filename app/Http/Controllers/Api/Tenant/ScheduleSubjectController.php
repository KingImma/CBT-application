<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Assessments\Actions\ScheduleSubjects\AssignScheduleSubject;
use App\Domains\Assessments\Actions\ScheduleSubjects\RemoveScheduleSubject;
use App\Domains\Assessments\Actions\ScheduleSubjects\UpdateScheduleSubject;
use App\Domains\Assessments\Data\Input\ScheduleSubjectData;
use App\Domains\Assessments\Data\Output\ScheduleSubjectData as ScheduleSubjectOutput;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\ScheduleSubject;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group Assessment Schedule — Subject Calendar
 * Per-subject exam windows nested inside an Assessment's exam-period ceiling.
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
     * @urlParam assessment string required The assessment (schedule) UUID.
     */
    public function index(Assessment $assessment): JsonResponse
    {
        Gate::authorize('view', $assessment);

        $slots = $assessment->scheduleSubjects()
            ->with('subject:id,name,code')
            ->orderBy('starts_at')
            ->get();

        return ApiResponse::success(
            ScheduleSubjectOutput::collect($slots),
            'Schedule subjects retrieved successfully.'
        );
    }

    /**
     * Place a subject into the calendar. Rejects if outside the schedule's
     * exam period ceiling, or overlapping another subject in this schedule.
     *
     * @urlParam assessment string required The assessment (schedule) UUID.
     */
    public function store(ScheduleSubjectData $data, Assessment $assessment): JsonResponse
    {
        Gate::authorize('manageSchedule', $assessment);

        $slot = $this->assign->execute($assessment, $data);

        return ApiResponse::created(
            ScheduleSubjectOutput::from($slot->load('subject:id,name,code')),
            'Subject scheduled.'
        );
    }

    /**
     * Move/resize a subject's slot. Same ceiling + overlap checks apply,
     * excluding itself from the overlap comparison.
     *
     * @urlParam assessment string required The assessment (schedule) UUID.
     * @urlParam scheduleSubject string required The schedule-subject UUID.
     */
    public function update(ScheduleSubjectData $data, Assessment $assessment, ScheduleSubject $scheduleSubject): JsonResponse
    {
        Gate::authorize('manageSchedule', $assessment);

        abort_unless($scheduleSubject->assessment_id === $assessment->id, 404);

        $slot = $this->update->execute($scheduleSubject, $data);

        return ApiResponse::success(
            ScheduleSubjectOutput::from($slot->load('subject:id,name,code')),
            'Subject slot updated.'
        );
    }

    /**
     * Remove a subject slot. Blocked once the schedule is activated.
     *
     * @urlParam assessment string required The assessment (schedule) UUID.
     * @urlParam scheduleSubject string required The schedule-subject UUID.
     */
    public function destroy(Assessment $assessment, ScheduleSubject $scheduleSubject): JsonResponse
    {
        Gate::authorize('manageSchedule', $assessment);

        abort_unless($scheduleSubject->assessment_id === $assessment->id, 404);

        $this->remove->execute($scheduleSubject);

        return ApiResponse::message('Subject slot removed.');
    }
}