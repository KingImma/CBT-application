<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Assessments\Actions\ActivateAssessment;
use App\Domains\Assessments\Actions\CloseSubmissions;
use App\Domains\Assessments\Actions\CompleteAssessment;
use App\Domains\Assessments\Actions\CreateAssessmentSchedule;
use App\Domains\Assessments\Actions\DeleteAssessmentSchedule;
use App\Domains\Assessments\Actions\ReopenSubmissions;
use App\Domains\Assessments\Actions\PublishScheduleResults;
use App\Domains\Assessments\Actions\UpdateAssessmentSchedule;
use App\Domains\Assessments\Data\Input\CreateScheduleData;
use App\Domains\Assessments\Data\Input\UpdateScheduleData;
use App\Domains\Assessments\Data\Output\ScheduleData;
use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * @group Assessment Schedules
 *
 * Scheduled occurrences of an assessment, one per session/term. Creating a
 * schedule immediately opens its teacher question window; the student exam
 * phase runs draft → active → completed.
 */
class AssessmentScheduleController extends Controller
{
    public function __construct(
        private CreateAssessmentSchedule $createSchedule,
        private UpdateAssessmentSchedule $updateSchedule,
        private DeleteAssessmentSchedule $deleteSchedule,
        private CloseSubmissions $closeSubmissions,
        private ReopenSubmissions $reopenSubmissions,
        private ActivateAssessment $activateAssessment,
        private CompleteAssessment $completeAssessment,
        private PublishScheduleResults $publishResults
    ) {}

    /** List every occurrence of an assessment (the reuse view). */
    public function index(Assessment $assessment, Request $request): JsonResponse
    {
        Gate::authorize('view', $assessment);

        // Teachers only see occurrences for class levels they're assigned to;
        // admins see everything (visibility now lives on the occurrence).
        $user = $request->user('tenant');
        $isAdmin = $user->hasRole(RoleType::SchoolAdmin->value);

        $schedules = $assessment->schedules()
            ->when(! $isAdmin, fn ($q) => $q->whereIn(
                'class_level_id',
                TeacherSubjectAssignment::query()
                    ->select('class_level_id')
                    ->where('user_id', $user->id)
            ))
            ->with(['term', 'academicSession', 'classLevel:id,name'])
            ->withCount('submissions as submission_count')
            ->orderByDesc('created_at')
            ->get();

        return ApiResponse::success(
            ScheduleData::collect($schedules),
            'Assessment schedules retrieved successfully.'
        );
    }

    /** Schedule the assessment into the current session/term; opens the question window. */
    public function store(CreateScheduleData $data, Assessment $assessment): JsonResponse
    {
        // Admin-only via the parent assessment's update ability.
        Gate::authorize('update', $assessment);

        $schedule = $this->createSchedule->execute($assessment, $data);

        return ApiResponse::created(
            ScheduleData::from($schedule->load(['classLevel', 'classArm', 'term', 'academicSession'])),
            'Assessment scheduled.'
        );
    }

    public function show(AssessmentSchedule $schedule): JsonResponse
    {
        Gate::authorize('view', $schedule);

        $schedule->load([
            'term',
            'academicSession',
            'scheduleSubjects.subject:id,name,code',
            'submissions.teacher:id,first_name,last_name',
            'submissions.subject:id,name',
        ])->loadCount('submissions as submission_count');

        return ApiResponse::success(ScheduleData::from($schedule), 'Assessment schedule retrieved successfully.');
    }

    public function update(UpdateScheduleData $data, AssessmentSchedule $schedule): JsonResponse
    {
        Gate::authorize('manage', $schedule);

        return ApiResponse::success(
            ScheduleData::from($this->updateSchedule->execute($schedule, $data)->load(['classLevel', 'classArm', 'term', 'academicSession'])),
            'Assessment schedule updated.'
        );
    }

    public function destroy(AssessmentSchedule $schedule): JsonResponse
    {
        Gate::authorize('manage', $schedule);

        $this->deleteSchedule->execute($schedule);

        return ApiResponse::message('Assessment schedule deleted.');
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function closeSubmissions(AssessmentSchedule $schedule): JsonResponse
    {
        Gate::authorize('manage', $schedule);

        return ApiResponse::success(
            ScheduleData::from($this->closeSubmissions->execute($schedule)),
            'Question submission window closed.'
        );
    }

    public function reopen(Request $request, AssessmentSchedule $schedule): JsonResponse
    {
        Gate::authorize('manage', $schedule);

        $validated = $request->validate([
            'question_submission_ends' => ['required', 'date'],
        ]);

        return ApiResponse::success(
            ScheduleData::from($this->reopenSubmissions->execute($schedule, $validated['question_submission_ends'])),
            'Question submission window reopened.'
        );
    }

    public function activate(AssessmentSchedule $schedule): JsonResponse
    {
        Gate::authorize('manage', $schedule);

        return ApiResponse::success(
            ScheduleData::from($this->activateAssessment->execute($schedule)),
            'Assessment activated.'
        );
    }

    public function complete(AssessmentSchedule $schedule): JsonResponse
    {
        Gate::authorize('manage', $schedule);

        return ApiResponse::success(
            ScheduleData::from($this->completeAssessment->execute($schedule)),
            'Assessment completed.'
        );
    }

    public function publishResults(AssessmentSchedule $schedule): JsonResponse
    {
        Gate::authorize('manage', $schedule);
    
        $results = $this->publishResults->execute($schedule);
    
        $publishedCount = collect($results)->where('status', 'published')->count();
        $skippedCount = collect($results)->where('status', 'skipped')->count();
    
        return ApiResponse::success(
            ['results' => $results],
            "{$publishedCount} exam(s) published".($skippedCount ? ", {$skippedCount} skipped." : '.')
        );
    }
}
