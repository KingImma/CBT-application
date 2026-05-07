<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\CreateExamAction;
use App\Actions\Tenants\Exam\UpdateExamAction;
use App\Actions\Tenants\Exam\DeleteExamAction;
use App\Actions\Tenants\Exam\PublishExamAction;
use App\Actions\Tenants\Exam\StartExamSessionAction;
use App\Actions\Tenants\Exam\SyncExamTopicsAction;
use App\Http\Controllers\Controller;
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
        private CreateExamAction $createAction,
        private UpdateExamAction $updateAction,
        private DeleteExamAction $deleteAction,
        private PublishExamAction $publishAction,
        private StartExamSessionAction $startSessionAction,
        private SyncExamTopicsAction $syncTopicsAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);
        
        $exams = Exam::with(['subject', 'classLevel', 'creator:id,first_name,last_name'])
            ->when($request->status, fn ($q, $status) => $q->byStatus($status))
            ->when($request->subject_id, fn ($q, $id) => $q->bySubject($id))
            ->when($request->class_level_id, fn ($q, $id) => $q->byClass($id))
            ->when($request->class_arm_id, fn ($q, $id) => $q->byClassArm($id))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::paginated($exams, 'Exams retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
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
            'settings' => ['nullable', 'array'],
            'instructions' => ['nullable', 'string'],
            'topic_ids' => ['sometimes', 'array'],
            'topic_ids.*' => ['uuid', 'exists:topics,id'],
        ]);

        $exam = $this->createAction->execute(array_merge($validated, [
            'created_by' => $request->user('tenant')->id,
        ]));

        if (! empty($validated['topic_ids'])) {
            $this->syncTopicsAction->execute($exam, $validated['topic_ids']);
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

        return ApiResponse::success($exam, 'Exam retrieved successfully.');
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
            'settings' => ['sometimes', 'nullable', 'array'],
            'instructions' => ['sometimes', 'nullable', 'string'],
        ]);

        $exam = $this->updateAction->execute($exam, $validated);

        return ApiResponse::success(
            $exam->load(['subject', 'classLevel']),
            'Exam updated.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('delete', $exam);

        $this->deleteAction->execute($exam);

        return ApiResponse::message('Exam deleted.');
    }

    public function publish(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('publish', $exam);

        $exam = $this->publishAction->execute($exam);

        return ApiResponse::success($exam, 'Exam published and scheduled.');
    }

    public function startSession(string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $this->authorize('startSession', $exam);

        $exam = $this->startSessionAction->execute($exam);

        return ApiResponse::success($exam, 'Exam session started.');
    }
}
