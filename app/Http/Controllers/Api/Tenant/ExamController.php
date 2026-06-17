<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ManageExam;
use App\Actions\Tenants\Exam\ManageExamSession;
use App\Data\Exam\ExamData;
use App\Enums\RoleType;
use App\Exceptions\Domain\Exam\ExamCannotBeActivatedException;
use App\Exceptions\Domain\Exam\ExamCannotBeCompletedException;
use App\Exceptions\Domain\Exam\ExamCannotBeSubmittedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreExamRequest;
use App\Models\Tenant\Exam;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Exam Administration
 * * APIs for scheduling CBT sessions, attaching questions, live monitoring, and grading.
 */
class ExamController extends Controller
{
    public function __construct(
        private ManageExam $crudAction,
        private ManageExamSession $sessionAction,
    ) {}

    /**
     * List all exams with optional filters.
     *
     * @subgroup Exam Management
     *
     * @queryParam status string Filter by status (draft, submitted, active, completed). No-example
     * @queryParam subject_id string Filter by subject UUID. No-example
     * @queryParam class_level_id string Filter by class level UUID. No-example
     * @queryParam class_arm_id string Filter by class arm UUID. No-example
     * @queryParam per_page int Results per page (default: 20). No-example
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);
        $user = $request->user('tenant');

        $exams = Exam::select(
            'id',
            'title',
            'type',
            'status',
            'subject_id',
            'class_level_id',
            'class_arm_id',
            'term_id',
            'created_by',
            'total_marks',
            'pass_mark',
            'duration_minutes',
            'max_attempts',
            'scheduled_start',
            'instructions',
            'created_at',
            'expected_attempts',
            'completed_attempts',
            'published_at',
        )
            ->with([
                'subject',
                'classLevel',
                'classArm',
                'term',
                'creator:id,first_name,last_name',
            ])
            ->withCount('examQuestions as question_count')
            ->when($request->status, fn ($q, $status) => $q->byStatus($status))
            ->when($request->subject_id, fn ($q, $id) => $q->bySubject($id))
            ->when(
                $request->class_level_id,
                fn ($q, $id) => $q->byClassLevel($id),
            )
            ->when($request->class_arm_id, fn ($q, $id) => $q->byClassArm($id))
            ->when($user && $user->role === RoleType::Teacher->value, fn ($q) => $q->where('created_by', $user->id))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $exams,
            'Exams retrieved successfully.',
            ExamData::collect($exams->getCollection()),
        );
    }

    /**
     * Create a new exam.
     *
     * @subgroup Exam Management
     *
     * @bodyParam title string required The exam title. Example: "Mid-Term Mathematics"
     * @bodyParam subject_id string required The subject UUID. No-example
     * @bodyParam class_level_id string required The class level UUID. No-example
     * @bodyParam class_arm_id string nullable The class arm UUID. No-example
     * @bodyParam term_id string required The term UUID. No-example
     * @bodyParam duration_minutes int Exam duration in minutes. Example: 120
     * @bodyParam pass_mark numeric Pass mark percentage. Example: 50
     * @bodyParam max_attempts int Maximum attempts per student. Example: 1
     * @bodyParam scheduled_start string nullable ISO 8601 scheduled start datetime. No-example
     * @bodyParam instructions string nullable Exam instructions for students. No-example
     */
    public function store(StoreExamRequest $request): JsonResponse
    {
        $this->authorize('create', Exam::class);

        $exam = $this->crudAction->create(
            array_merge($request->validatedData()->toArray(), [
                'created_by' => $request->user('tenant')->id,
            ]),
        );

        return ApiResponse::created(
            $exam->load(['subject', 'classLevel']),
            'Exam created.',
        );
    }

    /**
     * Get a single exam with its questions.
     *
     * @subgroup Exam Management
     *
     * @urlParam id string required The exam UUID.
     */
    public function show(string $id): JsonResponse
    {
        $exam = Exam::with([
            'subject',
            'classLevel',
            'classArm',
            'term',
            'creator:id,first_name,last_name',
            'examQuestions.question.options',
        ])
            ->withCount('examQuestions as question_count')
            ->findOrFail($id);

        $this->authorize('view', $exam);

        return ApiResponse::success(
            ExamData::from($exam),
            'Exam retrieved successfully.',
        );
    }

    /**
     * Update an existing exam.
     *
     * @subgroup Exam Management
     *
     * @urlParam id string required The exam UUID.
     *
     * @bodyParam title string The exam title. No-example
     * @bodyParam subject_id string The subject UUID. No-example
     * @bodyParam class_level_id string The class level UUID. No-example
     * @bodyParam class_arm_id string nullable The class arm UUID. No-example
     * @bodyParam term_id string The term UUID. No-example
     * @bodyParam duration_minutes int Exam duration in minutes. No-example
     * @bodyParam pass_mark numeric nullable Pass mark percentage. No-example
     * @bodyParam max_attempts int Maximum attempts per student. No-example
     * @bodyParam scheduled_start string nullable ISO 8601 scheduled start. No-example
     * @bodyParam instructions string nullable Exam instructions. No-example
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('update', $exam);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'subject_id' => ['sometimes', 'uuid', 'exists:subjects,id'],
            'class_level_id' => ['sometimes', 'uuid', 'exists:class_levels,id'],
            'class_arm_id' => [
                'sometimes',
                'nullable',
                'uuid',
                'exists:class_arms,id',
            ],
            'term_id' => ['sometimes', 'uuid', 'exists:terms,id'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1'],
            'pass_mark' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_attempts' => ['sometimes', 'integer', 'min:1'],
            'scheduled_start' => ['sometimes', 'nullable', 'date'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'settings.randomize_questions' => ['sometimes', 'boolean'],
            'settings.show_result_immediately' => ['sometimes', 'boolean'],
            'settings.results_release_date' => [
                'sometimes',
                'nullable',
                'date',
            ],
        ]);

        $exam = $this->crudAction->update($exam, $validated);

        return ApiResponse::success(
            $exam->load(['subject', 'classLevel']),
            'Exam updated.',
        );
    }

    /**
     * Delete an exam.
     *
     * @subgroup Exam Management
     *
     * @urlParam id string required The exam UUID.
     */
    public function destroy(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('delete', $exam);

        $this->crudAction->delete($exam);

        return ApiResponse::message('Exam deleted.');
    }

    /**
     * Submit an exam for review by an administrator.
     *
     * @subgroup Exam Workflow
     *
     * @urlParam id string required The exam UUID.
     */
    public function submitForReview(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('submitForReview', $exam);

        try {
            $exam->submitForReview()->save();
        } catch (ExamCannotBeSubmittedException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam submitted for review.');
    }

    /**
     * Activate an exam, making it visible to students.
     * Students can start the exam when scheduled_start is reached.
     *
     * @subgroup Exam Workflow
     *
     * @urlParam id string required The exam UUID.
     */
    public function activate(string $id, Request $request): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('activate', $exam);

        try {
            $exam->activate($request->user('tenant')->id)->save();
        } catch (ExamCannotBeActivatedException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam activated.');
    }

    /**
     * Publish an exam once it's completed and ready for student access.
     *
     * @subgroup Exam Workflow
     *
     * @urlParam id string required The exam UUID.
     */
    /**
     * Publish an exam to make it available to students.
     *
     * @subgroup Exam Workflow
     *
     * @urlParam id string required The exam UUID.
     */
    public function publish(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('publish', $exam);

        try {
            $exam->publish()->save();
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam published.');
    }

    /**
     * Publish results for an exam, making them visible to students.
     *
     * @subgroup Exam Workflow
     *
     * @urlParam id string required The exam UUID.
     */
    public function publishResults(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('publishResults', $exam);

        $exam->publish()->save();

        return ApiResponse::success($exam, 'Results published.');
    }

    /**
     * Unpublish results for an exam, rolling the status back to Completed
     * and hiding results from students.
     *
     * @subgroup Exam Workflow
     *
     * @urlParam id string required The exam UUID.
     */
    public function unpublishResults(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('unpublishResults', $exam);

        $exam->unpublish()->save();

        return ApiResponse::success($exam, 'Results unpublished.');
    }

    /**
     * Force-complete an active exam immediately, bypassing window
     * and completed attempt checks.
     *
     * @subgroup Exam Workflow
     *
     * @urlParam id string required The exam UUID.
     */
    public function forceComplete(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('forceComplete', $exam);

        try {
            $exam->complete()->save();
        } catch (ExamCannotBeCompletedException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            ExamData::from($exam->load(['subject', 'classLevel'])),
            'Exam ended successfully.'
        );
    }
}
