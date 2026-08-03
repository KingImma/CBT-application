<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Questions\Actions\CloneQuestion;
use App\Domains\Questions\Data\QuestionData;
use App\Enums\QuestionType;
use App\Enums\RoleType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
     * The response contains a polymorphic array of question objects.
     * Each question's shape depends on its type field:
     * - mcq: includes options with id, content, is_correct, label, order
     * - true_false: includes options with id, content, is_correct, label, order
     * - fill_in_blank: includes acceptable_answers with content, case_sensitive
     *
     * @subgroup Questions
     *
     * @queryParam subject_id string Filter by subject UUID. No-example
     * @queryParam class_level_id string Filter by class level UUID. No-example
     * @queryParam search string Search question content. No-example
     * @queryParam include_inactive bool Include inactive questions (default: false). No-example
     * @queryParam per_page int Results per page (default: 20). No-example
     *
     * @responseField data.*.id string The question UUID.
     * @responseField data.*.type string The question type: mcq, true_false, or fill_in_blank.
     * @responseField data.*.content string Question text.
     * @responseField data.*.options array|null Present for mcq/true_false. Array of {id, content, is_correct, label, order}.
     * @responseField data.*.acceptable_answers array|null Present for fill_in_blank. Array of {content, case_sensitive}.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user('tenant');

        $questions = Question::select('id', 'type', 'content', 'image_url', 'subject_id', 'class_level_id', 'created_by', 'is_active', 'created_at')
            ->with(['classLevel', 'subject', 'creator:id,first_name,last_name'])
            ->when($user && $user->role === RoleType::Teacher->value, fn ($q) => $q->where('created_by', $user->id))
            ->when($request->subject_id, fn ($q) => $q->where('subject_id', $request->subject_id))
            ->when($request->class_level_id, fn ($q) => $q->where('class_level_id', $request->class_level_id))
            ->when($request->search, fn ($q) => $q->where('content', 'ilike', "%{$request->search}%"))
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->with('options')
            ->orderByDesc('created_at')
            ->paginate((int) $request->get('per_page', 20));

        $questionDtos = $questions->getCollection()->map(
            fn (Question $q) => QuestionData::fromQuestion($q)
        );

        return ApiResponse::paginated(
            $questions,
            'Questions retrieved successfully.',
            $questionDtos,
        );
    }

    /**
     * Type-specific validation rules for question options.
     *
     * @return array<string, mixed>
     */
    private function optionRulesForType(string $type): array
    {
        if ($type === QuestionType::FillInBlank->value) {
            return [
                'options' => ['required', 'array', 'min:1'],
                'options.*.content' => ['required', 'string'],
                'options.*.content_format' => ['nullable', 'string', 'in:plain_text,latex'],
                'options.*.is_correct' => ['prohibited'],
                'options.*.order' => ['nullable', 'integer'],
                'options.*.label' => ['nullable', 'string', 'max:10'],
                'options.*.match_pair' => ['nullable', 'string', 'max:255'],
            ];
        }

        return [
            'options' => ['required', 'array', 'min:2'],
            'options.*.content' => ['required', 'string'],
            'options.*.content_format' => ['nullable', 'string', 'in:plain_text,latex'],
            'options.*.is_correct' => ['required', 'boolean'],
            'options.*.order' => ['nullable', 'integer'],
            'options.*.label' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('createForClass', [Question::class, $request->input('class_level_id')]);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_column(QuestionType::cases(), 'value'))],
            'subject_id' => [
                'required', 'uuid', 'exists:subjects,id',
                function ($attribute, $value, $fail) use ($request) {
                    $classLevelId = $request->input('class_level_id');
                    if ($classLevelId === null) {
                        return;
                    }
                    $exists = ClassLevel::where('id', $classLevelId)
                        ->whereHas('subjects', fn ($q) => $q->where('subject_id', $value))
                        ->exists();
                    if (! $exists) {
                        $fail('The selected subject is not assigned to the selected class level.');
                    }
                },
            ],
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'content' => ['required', 'string'],
            'content_format' => ['sometimes', 'string', 'in:plain_text,latex'],
            'image_url' => ['nullable', 'url', 'max:500'],
        ] + $this->optionRulesForType($request->input('type', '')));

        $type = $validated['type'];

        // Validate correct-option count against type rules
        $correctOptionError = $this->validateCorrectOptionCount($type, $validated['options'] ?? []);
        if ($correctOptionError !== null) {
            return ApiResponse::error($correctOptionError, 422);
        }

        $question = DB::transaction(function () use ($validated, $request, $type) {
            $question = Question::create([
                'subject_id' => $validated['subject_id'],
                'class_level_id' => $validated['class_level_id'],
                'type' => $type,
                'content' => $validated['content'],
                'image_url' => $validated['image_url'] ?? null,
                'is_active' => true,
                'usage_count' => 0,
                'created_by' => $request->user('tenant')->id,
            ]);

            foreach ($validated['options'] as $i => $opt) {
                // FITB: all options are acceptable answers — is_correct always true
                $isCorrect = $type === QuestionType::FillInBlank->value ? true : $opt['is_correct'];

                QuestionOption::create([
                    'question_id' => $question->id,
                    'label' => $opt['label'] ?? null,
                    'content' => $opt['content'],
                    'content_format' => $opt['content_format'] ?? 'plain_text',
                    'is_correct' => $isCorrect,
                    'order' => $opt['order'] ?? $i,
                    'match_pair' => $opt['match_pair'] ?? null,
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
     * The question shape depends on its type field:
     * - mcq: includes options with id, content, is_correct, label, order
     * - true_false: includes options with id, content, is_correct, label, order
     * - fill_in_blank: includes acceptable_answers with content, case_sensitive
     *
     * @subgroup Questions
     *
     * @urlParam id string required The question UUID.
     *
     * @responseField data.id string The question UUID.
     * @responseField data.type string The question type: mcq, true_false, or fill_in_blank.
     * @responseField data.options array|null Present for mcq/true_false.
     * @responseField data.acceptable_answers array|null Present for fill_in_blank.
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

        return ApiResponse::success(
            QuestionData::fromQuestion($question),
            'Question retrieved successfully.',
        );
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $question = Question::findOrFail($id);
        $this->authorize('update', $question);

        $baseRules = [
            'content' => ['sometimes', 'string'],
            'content_format' => ['sometimes', 'string', 'in:plain_text,latex'],
            'default_marks' => ['sometimes', 'numeric', 'min:0.5', 'max:100'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        $optionRules = [];
        if ($request->has('options')) {
            if ($question->type === QuestionType::FillInBlank->value) {
                $optionRules = [
                    'options' => ['sometimes', 'array', 'min:1'],
                    'options.*.id' => ['nullable', 'uuid'],
                    'options.*.content' => ['required_with:options', 'string'],
                    'options.*.content_format' => ['nullable', 'string', 'in:plain_text,latex'],
                    'options.*.is_correct' => ['prohibited'],
                    'options.*.order' => ['nullable', 'integer'],
                    'options.*.label' => ['nullable', 'string', 'max:10'],
                    'options.*.match_pair' => ['nullable', 'string', 'max:255'],
                ];
            } else {
                $optionRules = [
                    'options' => ['sometimes', 'array', 'min:2'],
                    'options.*.id' => ['nullable', 'uuid'],
                    'options.*.content' => ['required_with:options', 'string'],
                    'options.*.content_format' => ['nullable', 'string', 'in:plain_text,latex'],
                    'options.*.is_correct' => ['required_with:options', 'boolean'],
                    'options.*.order' => ['nullable', 'integer'],
                    'options.*.label' => ['nullable', 'string', 'max:10'],
                ];
            }
        }

        $validated = $request->validate(array_merge($baseRules, $optionRules));

        // Validate correct-option count if options are included in the update
        if (isset($validated['options'])) {
            $correctOptionError = $this->validateCorrectOptionCount(
                $question->type,
                $validated['options'],
            );
            if ($correctOptionError !== null) {
                return ApiResponse::error($correctOptionError, 422);
            }
        }

        DB::transaction(function () use ($question, $validated) {
            $question->update(collect($validated)->except('options')->toArray());

            if (isset($validated['options'])) {
                $keptOptionIds = collect($validated['options'])->pluck('id')->filter()->toArray();
                $question->options()->whereNotIn('id', $keptOptionIds)->delete();

                foreach ($validated['options'] as $index => $opt) {
                    $isCorrect = $question->type === QuestionType::FillInBlank->value
                        ? true
                        : $opt['is_correct'];

                    QuestionOption::updateOrCreate(
                        ['id' => $opt['id'] ?? null, 'question_id' => $question->id],
                        [
                            'content' => $opt['content'],
                            'content_format' => $opt['content_format'] ?? 'plain_text',
                            'is_correct' => $isCorrect,
                            'label' => $opt['label'] ?? null,
                            'order' => $opt['order'] ?? $index,
                            'match_pair' => $opt['match_pair'] ?? null,
                        ]
                    );
                }
            }
        });

        return ApiResponse::success($question->fresh(['options']), 'Question updated.');
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

        $count = app(CloneQuestion::class)->cloneToTerm(
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

    /**
     * Validate correct-option count against question type rules.
     *
     * Returns an error message string, or null when valid.
     *
     * Rules:
     *   FillInBlank  — skip (all options are acceptable answers by definition)
     *   TrueFalse    — exactly 1 correct (maxCorrectOptions = 1)
     *   MCQ          — ≥1 correct, but not ALL options correct
     */
    private function validateCorrectOptionCount(string $type, array $options): ?string
    {
        $questionType = QuestionType::tryFrom($type);

        // FITB doesn't use is_correct for MCQ-style checking
        if ($questionType === null || $questionType === QuestionType::FillInBlank) {
            return null;
        }

        $totalCount = count($options);
        $correctCount = collect($options)->where('is_correct', true)->count();

        // At least one correct required for all choice-based types
        if ($correctCount === 0) {
            return "At least one correct option is required for {$type}.";
        }

        $maxCorrect = $questionType->maxCorrectOptions();

        // TrueFalse: maxCorrectOptions() = 1, so enforce exactly 1
        if ($maxCorrect !== null && $correctCount > $maxCorrect) {
            return "{$type} allows at most {$maxCorrect} correct option(s). Got {$correctCount}.";
        }

        // MCQ: maxCorrectOptions() = null (no enum-defined cap), but all-correct is nonsensical —
        // it would mean there is no wrong answer, making the question unanswerable for grading.
        if ($questionType === QuestionType::Mcq && $correctCount === $totalCount) {
            return 'MCQ cannot have all options marked as correct. At least one option must be incorrect.';
        }

        return null;
    }
}
