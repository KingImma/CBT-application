<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\CloneQuestionAction;
use App\Data\Question\QuestionData;
use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @group Question Bank
 *
 * APIs for creating and managing objective and theory questions.
 */
class QuestionController extends Controller
{
    /**
     * List questions with optional filters.
     *
     * @subgroup Questions
     *
     * @queryParam subject_id string Filter by subject UUID. No-example
     * @queryParam class_level_id string Filter by class level UUID. No-example
     * @queryParam search string Search question content. No-example
     * @queryParam include_inactive bool Include inactive questions (default: false). No-example
     * @queryParam per_page int Results per page (default: 20). No-example
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('tenant');

        $questions = Question::select('id', 'type', 'content', 'default_marks', 'image_url', 'subject_id', 'class_level_id', 'created_by', 'is_active', 'created_at')
            ->with(['classLevel', 'subject', 'creator:id,first_name,last_name'])
            ->when($user && $user->role === RoleType::Teacher->value, fn ($q) => $q->where('created_by', $user->id))
            ->when($request->subject_id, fn ($q) => $q->where('subject_id', $request->subject_id))
            ->when($request->class_level_id, fn ($q) => $q->where('class_level_id', $request->class_level_id))
            ->when($request->search, fn ($q) => $q->where('content', 'ilike', "%{$request->search}%"))
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->with('options')
            ->orderByDesc('created_at')
            ->paginate((int) $request->get('per_page', 20));

        return ApiResponse::paginated(
            $questions,
            'Questions retrieved successfully.',
            QuestionData::collect($questions->getCollection())
        );
    }

    /**
     * Create a new question with options.
     *
     * @subgroup Questions
     *
     * @bodyParam subject_id string required The subject UUID. Must be assigned to the class level. No-example
     * @bodyParam class_level_id string required The class level UUID. No-example
     * @bodyParam content string required The question text. Example: "What is the capital of Nigeria?"
     * @bodyParam default_marks numeric required Default mark for the question (0.5 - 100). Example: 2
     * @bodyParam image_url string nullable URL to an image for the question. No-example
     * @bodyParam options array required Array of answer options (minimum 2). No-example
     * @bodyParam options.*.content string required Option text. No-example
     * @bodyParam options.*.is_correct boolean required Whether this is the correct option (exactly one must be true). No-example
     * @bodyParam options.*.order int nullable Display order. No-example
     * @bodyParam options.*.label string nullable Short label (max 10 chars). No-example
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('createForClass', [Question::class, $request->input('class_level_id')]);

        $validated = $request->validate([
            'subject_id' => [
                'required', 'uuid', 'exists:subjects,id',
                function ($attribute, $value, $fail) use ($request) {
                    $classLevelId = $request->input('class_level_id');
                    if ($classLevelId) {
                        $exists = ClassLevel::where('id', $classLevelId)
                            ->whereHas('subjects', fn ($q) => $q->where('subject_id', $value))
                            ->exists();
                        if (! $exists) {
                            $fail('The selected subject is not assigned to the selected class level.');
                        }
                    }
                },
            ],
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'content' => ['required', 'string'],
            'default_marks' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'options' => ['required', 'array', 'min:2'],
            'options.*.content' => ['required', 'string'],
            'options.*.is_correct' => ['required', 'boolean'],
            'options.*.order' => ['nullable', 'integer'],
            'options.*.label' => ['nullable', 'string', 'max:10'],
        ]);

        $correctCount = collect($validated['options'])->where('is_correct', true)->count();
        if ($correctCount !== 1) {
            return ApiResponse::error('MCQ must have exactly one correct option.', 422);
        }

        $question = DB::transaction(function () use ($validated, $request) {
            $question = Question::create([
                'subject_id' => $validated['subject_id'],
                'class_level_id' => $validated['class_level_id'],
                'type' => 'mcq_single',
                'content' => $validated['content'],
                'default_marks' => $validated['default_marks'],
                'image_url' => $validated['image_url'] ?? null,
                'is_active' => true,
                'usage_count' => 0,
                'created_by' => $request->user('tenant')->id,
            ]);

            foreach ($validated['options'] as $i => $opt) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'label' => $opt['label'] ?? null,
                    'content' => $opt['content'],
                    'is_correct' => $opt['is_correct'],
                    'order' => $opt['order'] ?? $i,
                ]);
            }

            return $question;
        });

        return ApiResponse::created(
            $question->load(['options', 'classLevel']),
            'Question created.'
        );
    }

    /**
     * Get a single question with its options.
     *
     * @subgroup Questions
     *
     * @urlParam id string required The question UUID.
     */
    public function show(string $id): JsonResponse
    {
        $question = Question::with([
            'options',
            'classLevel',
            'subject',
            'creator:id,first_name,last_name',
        ])->findOrFail($id);

        $this->authorize('view', $question);

        return ApiResponse::success(QuestionData::from($question), 'Question retrieved successfully.');
    }

    /**
     * Update a question.
     *
     * @subgroup Questions
     *
     * @urlParam id string required The question UUID.
     *
     * @bodyParam content string Question text. No-example
     * @bodyParam default_marks numeric Default mark (0.5 - 100). No-example
     * @bodyParam image_url string nullable Image URL. No-example
     * @bodyParam is_active boolean Whether the question is active. No-example
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $question = Question::findOrFail($id);
        $this->authorize('update', $question);

        $validated = $request->validate([
            'content' => ['sometimes', 'string'],
            'default_marks' => ['sometimes', 'numeric', 'min:0.5', 'max:100'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $question->update($validated);

        return ApiResponse::success(
            $question->fresh(['options']),
            'Question updated.'
        );
    }

    /**
     * Archive a question (soft delete).
     *
     * @subgroup Questions
     *
     * @urlParam id string required The question UUID.
     */
    public function destroy(string $id): JsonResponse
    {
        $question = Question::findOrFail($id);
        $this->authorize('delete', $question);
        $question->delete();

        return ApiResponse::message('Question archived.');
    }

    /**
     * Restore an archived question.
     *
     * @subgroup Questions
     *
     * @urlParam id string required The question UUID.
     */
    public function restore(string $id): JsonResponse
    {
        $question = Question::withTrashed()->findOrFail($id);
        $question->restore();
        $question->update(['is_active' => true]);

        return ApiResponse::message('Question restored.');
    }

    /**
     * Clone questions from a previous term.
     *
     * @subgroup Term Operations
     *
     * @bodyParam source_session_id string required Source academic session UUID. No-example
     * @bodyParam source_term_id string required Source term UUID. No-example
     * @bodyParam target_session_id string required Target academic session UUID. No-example
     * @bodyParam target_term_id string required Target term UUID. No-example
     * @bodyParam subject_id string required Subject UUID. No-example
     * @bodyParam class_level_id string required Class level UUID. No-example
     */
    public function cloneFromTerm(Request $request): JsonResponse
    {
        $this->authorize('createFromClass', [Question::class, $request->input('class_level_id')]);

        $validated = $request->validate([
            'source_session_id' => ['required', 'uuid', 'exists:academic_sessions,id'],
            'source_term_id' => ['required', 'uuid', 'exists:terms,id'],
            'target_session_id' => ['required', 'uuid', 'exists:academic_sessions,id'],
            'target_term_id' => ['required', 'uuid', 'exists:terms,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
        ]);

        $count = app(CloneQuestionAction::class)->cloneToTerm(
            sourceSessionId: $validated['source_session_id'],
            sourceTermId: $validated['source_term_id'],
            targetSessionId: $validated['target_session_id'],
            targetTermId: $validated['target_term_id'],
            subjectId: $validated['subject_id'],
            classLevelId: $validated['class_level_id'],
            createdBy: $request->user('tenant')->id,
        );

        return ApiResponse::success(
            ['cloned_count' => $count],
            "{$count} questions cloned successfully."
        );
    }
}
