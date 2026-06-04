<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ExamCrudAction;
use App\Actions\Tenants\Exam\ExamSessionAction;
use App\Actions\Tenants\Exam\ExamStatusAction;
use App\Data\Exam\ExamData;
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
        private ExamCrudAction $crudAction,
        private ExamStatusAction $statusAction,
        private ExamSessionAction $sessionAction,
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

        $exams = Exam::select('id', 'title', 'type', 'status', 'subject_id', 'class_level_id', 'class_arm_id', 'term_id', 'created_by', 'total_marks', 'pass_mark', 'duration_minutes', 'max_attempts', 'scheduled_start', 'scheduled_end', 'instructions', 'created_at', 'expected_attempts', 'completed_attempts')
            ->with(['subject', 'classLevel', 'classArm', 'term', 'creator:id,first_name,last_name'])
            ->withCount('examQuestions')
            ->when($request->status, fn ($q, $status) => $q->byStatus($status))
            ->when($request->subject_id, fn ($q, $id) => $q->bySubject($id))
            ->when($request->class_level_id, fn ($q, $id) => $q->byClassLevel($id))
            ->when($request->class_arm_id, fn ($q, $id) => $q->byClassArm($id))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::paginated(
            $exams,
            'Exams retrieved successfully.',
            ExamData::collect($exams->getCollection())
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

        $exam = $this->crudAction->create(array_merge(
            $request->validatedData()->toArray(),
            ['created_by' => $request->user('tenant')->id],
        ));

        return ApiResponse::created(
            $exam->load(['subject', 'classLevel']),
            'Exam created.'
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
            'subject', 'classLevel', 'classArm', 'term', 'creator:id,first_name,last_name',
            'examQuestions.question.options',
        ])->findOrFail($id);

        $this->authorize('view', $exam);

        return ApiResponse::success(ExamData::from($exam), 'Exam retrieved successfully.');
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
            'class_arm_id' => ['sometimes', 'nullable', 'uuid', 'exists:class_arms,id'],
            'term_id' => ['sometimes', 'uuid', 'exists:terms,id'],
            'duration_minutes' => ['sometimes', 'integer', 'min:1'],
            'pass_mark' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'max_attempts' => ['sometimes', 'integer', 'min:1'],
            'scheduled_start' => ['sometimes', 'nullable', 'date'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'settings.randomize_questions' => ['sometimes', 'boolean'],
            'settings.show_result_immediately' => ['sometimes', 'boolean'],
            'settings.results_release_date' => ['sometimes', 'nullable', 'date'],
        ]);

        $exam = $this->crudAction->update($exam, $validated);

        return ApiResponse::success(
            $exam->load(['subject', 'classLevel']),
            'Exam updated.'
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
            $exam = $this->statusAction->submitForReview($exam);
        } catch (\RuntimeException $e) {
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
            $exam = $this->statusAction->activate($exam, $request->user('tenant')->id);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam activated.');
    }
}
