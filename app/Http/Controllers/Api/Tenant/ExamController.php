<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ExamCrudAction;
use App\Actions\Tenants\Exam\ExamPublishingAction;
use App\Actions\Tenants\Exam\ExamSessionAction;
use App\Actions\Tenants\Exam\ExamSessionLifecycleAction;
use App\Actions\Tenants\Exam\ExamStatusAction;
use App\Data\Exam\ExamData;
use App\Events\ActivityFeedEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreExamRequest;
use App\Models\Tenant\Exam;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function __construct(
        private ExamCrudAction $crudAction,
        private ExamStatusAction $statusAction,
        private ExamPublishingAction $publishingAction,
        private ExamSessionLifecycleAction $sessionLifecycleAction,
        private ExamSessionAction $sessionAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);

        $exams = Exam::select('id', 'title', 'type', 'status', 'subject_id', 'class_level_id', 'class_arm_id', 'term_id', 'created_by', 'total_marks', 'pass_mark', 'duration_minutes', 'max_attempts', 'scheduled_start', 'scheduled_end', 'instructions', 'created_at')
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
            ExamData::collection($exams->getCollection())
        );
    }

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

    public function show(string $id): JsonResponse
    {
        $exam = Exam::with([
            'subject', 'classLevel', 'classArm', 'term', 'creator:id,first_name,last_name',
            'examQuestions.question.options',
        ])->findOrFail($id);

        $this->authorize('view', $exam);

        return ApiResponse::success(ExamData::from($exam), 'Exam retrieved successfully.');
    }

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
            'scheduled_end' => ['sometimes', 'nullable', 'date', 'after:scheduled_start'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'settings' => ['nullable', 'array'],
            'settings.randomize_questions' => ['sometimes', 'boolean'],
            'settings.show_result_immediately' => ['sometimes', 'boolean'],
            'settings.results_release_date' => ['sometimes', 'nullable', 'date'],
            'settings.require_attendance' => ['sometimes', 'boolean'],
        ]);

        $exam = $this->crudAction->update($exam, $validated);

        return ApiResponse::success(
            $exam->load(['subject', 'classLevel']),
            'Exam updated.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('delete', $exam);

        $this->crudAction->delete($exam);

        return ApiResponse::message('Exam deleted.');
    }

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

    public function reject(Request $request, string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('reject', $exam);

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $exam = $this->statusAction->reject($exam, $validated['rejection_reason'] ?? null);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam rejected and returned to draft.');
    }

    public function recall(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('recall', $exam);

        try {
            $exam = $this->statusAction->recall($exam);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam recalled to draft.');
    }

    public function emergencyRevert(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('emergencyRevert', $exam);

        try {
            $exam = $this->statusAction->emergencyRevert($exam);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam emergency-reverted to draft.');
    }

    public function lock(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('lock', $exam);

        try {
            $exam = $this->statusAction->lock($exam);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam locked.');
    }

    public function unlock(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('unlock', $exam);

        try {
            $exam = $this->statusAction->unlock($exam);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam unlocked and returned to draft.');
    }

    public function publish(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('publish', $exam);

        $exam = $this->publishingAction->publish($exam);

        broadcast(new ActivityFeedEvent(
            channelType: 'school_admin',
            channelId: tenant('id'),
            action: 'exam.published',
            description: "Exam '{$exam->title}' published.",
            meta: ['exam_id' => $exam->id],
        ))->toOthers();

        return ApiResponse::success($exam, 'Exam published and scheduled.');
    }

    public function startSession(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('startSession', $exam);

        $exam = $this->sessionLifecycleAction->startSession($exam);

        return ApiResponse::success($exam, 'Exam session started.');
    }

    public function endSession(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('view', $exam);

        try {
            $exam = $this->sessionLifecycleAction->endSession($exam, $this->sessionAction);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam session ended.');
    }
}
