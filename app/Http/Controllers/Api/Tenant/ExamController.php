<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ExamCrudAction;
use App\Actions\Tenants\Exam\ExamLifecycleAction;
use App\Actions\Tenants\Exam\ExamSessionAction;
use App\Data\Schemas\ExamSettingsSchema;
use App\Events\ActivityFeedEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExamResource;
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
        private ExamLifecycleAction $lifecycleAction,
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
            ExamResource::collection($exams->getCollection())->resolve($request)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'title' => ['required', 'string', 'max:255'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'class_arm_id' => ['nullable', 'uuid', 'exists:class_arms,id'],
            'term_id' => ['required', 'uuid', 'exists:terms,id'],
            'type' => ['required', 'in:exam,test,quiz,mock,ca'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'pass_mark' => ['nullable', 'numeric', 'min:0'],
            'max_attempts' => ['nullable', 'integer', 'min:1'],
            'scheduled_start' => ['nullable', 'date'],
            'scheduled_end' => ['nullable', 'date', 'after:scheduled_start'],
            'instructions' => ['nullable', 'string'],
            'topic_ids' => ['sometimes', 'array'],
            'topic_ids.*' => ['uuid', 'exists:topics,id'],
        ], ExamSettingsSchema::validatorRules('settings')));

        $exam = $this->crudAction->create(array_merge($validated, [
            'created_by' => $request->user('tenant')->id,
        ]));

        if (! empty($validated['topic_ids'])) {
            $this->lifecycleAction->syncTopics($exam, $validated['topic_ids']);
        }

        return ApiResponse::created(
            $exam->load(['subject', 'classLevel', 'topics']),
            'Exam created.'
        );
    }

    public function show(string $id): JsonResponse
    {
        $exam = Exam::with([
            'subject', 'classLevel', 'classArm', 'term', 'creator:id,first_name,last_name',
            'examQuestions.question.options', 'examQuestions.question.topic', 'topics',
        ])->findOrFail($id);

        $this->authorize('view', $exam);

        return ApiResponse::success(new ExamResource($exam), 'Exam retrieved successfully.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('update', $exam);

        $validated = $request->validate(array_merge([
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
        ], ExamSettingsSchema::validatorRules('settings')));

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
            $exam = $this->lifecycleAction->submitForReview($exam);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam submitted for review.');
    }

    public function activate(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('activate', $exam);

        try {
            $exam = $this->lifecycleAction->activate($exam);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam activated.');
    }

    public function lock(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('lock', $exam);

        try {
            $exam = $this->lifecycleAction->lock($exam);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam locked.');
    }

    public function publish(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('publish', $exam);

        $exam = $this->lifecycleAction->publish($exam);

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

        $exam = $this->lifecycleAction->startSession($exam);

        return ApiResponse::success($exam, 'Exam session started.');
    }

    public function endSession(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('startSession', $exam);

        try {
            $exam = $this->lifecycleAction->endSession($exam, app(ExamSessionAction::class));
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($exam, 'Exam session ended.');
    }
}
