<?php

// - Full CRUD for the question bank matching the exact schema columns
// - Handles all 8 question types; options + fill_blank_answers created in one transaction
// - Validation rules differ per type (MCQ needs options, fill_blank needs answers)
// - Deliverable: teacher-facing question bank with filtering, soft delete, usage_count
// - Alternative: separate store endpoint per question type — explicit but 8x route surface

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FillBlankAnswer;
use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $questions = Question::with(['topic', 'classLevel', 'creator:id,first_name,last_name'])
            ->when($request->subject_id, fn ($q) => $q->where('subject_id', $request->subject_id)
            )
            ->when($request->class_level_id, fn ($q) => $q->where('class_level_id', $request->class_level_id)
            )
            ->when($request->topic_id, fn ($q) => $q->where('topic_id', $request->topic_id)
            )
            ->when($request->type, fn ($q) => $q->where('type', $request->type)
            )
            ->when($request->difficulty, fn ($q) => $q->where('difficulty', $request->difficulty)
            )
            ->when($request->search, fn ($q) => $q->where('content', 'ilike', "%{$request->search}%")
            )
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true)
            )
            ->with('topic', 'options')
            ->orderByDesc('created_at')
            ->paginate((int) $request->get('per_page', 20));

        return ApiResponse::paginated($questions, 'Questions retrieved successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'class_level_id' => ['required', 'uuid', 'exists:class_levels,id'],
            'topic_id' => ['nullable', 'uuid', 'exists:topics,id'],
            'type' => ['required', 'in:mcq_single,mcq_multi,true_false,fill_blank,short_answer,essay,matching,ordering'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'content' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
            'default_marks' => ['required', 'numeric', 'min:0.5', 'max:100'],
            'time_estimate_seconds' => ['nullable', 'integer', 'min:10'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'metadata' => ['nullable', 'array'],
            'metadata.tags' => ['nullable', 'array'],
            'metadata.source' => ['nullable', 'string'],
            'metadata.year' => ['nullable', 'integer'],

            // Options: required for choice-based types
            'options' => [
                'required_if:type,mcq_single,mcq_multi,true_false,matching,ordering',
                'array', 'min:2',
            ],
            'options.*.label' => ['nullable', 'string', 'max:10'],
            'options.*.content' => ['required_with:options', 'string'],
            'options.*.image_url' => ['nullable', 'url', 'max:500'],
            'options.*.is_correct' => ['required_with:options', 'boolean'],
            'options.*.order' => ['nullable', 'integer'],
            'options.*.match_pair' => ['nullable', 'string', 'max:255'],

            // Fill-blank answers: required for fill_blank type
            'fill_blank_answers' => ['required_if:type,fill_blank', 'array', 'min:1'],
            'fill_blank_answers.*.answer_text' => ['required_with:fill_blank_answers', 'string', 'max:255'],
            'fill_blank_answers.*.is_primary' => ['nullable', 'boolean'],
        ]);

        // Type-specific validation guards
        $typeError = $this->validateTypeRules($validated);
        if ($typeError) {
            return ApiResponse::error($typeError, 422);
        }

        $question = DB::transaction(function () use ($validated, $request) {
            $question = Question::create([
                'subject_id' => $validated['subject_id'],
                'class_level_id' => $validated['class_level_id'],
                'topic_id' => $validated['topic_id'] ?? null,
                'type' => $validated['type'],
                'difficulty' => $validated['difficulty'],
                'content' => $validated['content'],
                'explanation' => $validated['explanation'] ?? null,
                'default_marks' => $validated['default_marks'],
                'time_estimate_seconds' => $validated['time_estimate_seconds'] ?? null,
                'image_url' => $validated['image_url'] ?? null,
                'metadata' => $validated['metadata'] ?? null,
                'is_active' => true,
                'usage_count' => 0,
                'created_by' => $request->user('tenant')->id,
            ]);

            foreach ($validated['options'] ?? [] as $i => $opt) {
                QuestionOption::create([
                    'question_id' => $question->id,
                    'label' => $opt['label'] ?? null,
                    'content' => $opt['content'],
                    'image_url' => $opt['image_url'] ?? null,
                    'is_correct' => $opt['is_correct'],
                    'order' => $opt['order'] ?? $i,
                    'match_pair' => $opt['match_pair'] ?? null,
                ]);
            }

            foreach ($validated['fill_blank_answers'] ?? [] as $i => $ans) {
                FillBlankAnswer::create([
                    'question_id' => $question->id,
                    'answer_text' => $ans['answer_text'],
                    'is_primary' => $ans['is_primary'] ?? ($i === 0), // first answer = primary
                ]);
            }

            return $question;
        });

        return ApiResponse::created(
            $question->load(['options', 'fillBlankAnswers', 'topic', 'classLevel']),
            'Question created.'
        );
    }

    public function show(string $id): JsonResponse
    {
        $question = Question::with([
            'options',
            'fillBlankAnswers',
            'topic',
            'classLevel',
            'subject',
            'creator:id,first_name,last_name',
        ])->findOrFail($id);

        return ApiResponse::success($question, 'Question retrieved successfully.');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $question = Question::findOrFail($id);

        $validated = $request->validate([
            'topic_id' => ['sometimes', 'nullable', 'uuid', 'exists:topics,id'],
            'difficulty' => ['sometimes', 'in:easy,medium,hard'],
            'content' => ['sometimes', 'string'],
            'explanation' => ['sometimes', 'nullable', 'string'],
            'default_marks' => ['sometimes', 'numeric', 'min:0.5', 'max:100'],
            'time_estimate_seconds' => ['sometimes', 'nullable', 'integer'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $question->update($validated);

        return ApiResponse::success(
            $question->fresh(['options', 'fillBlankAnswers', 'topic']),
            'Question updated.'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        // - Soft deletes so exam history referencing this question stays intact
        $question = Question::findOrFail($id);
        $question->delete();

        return ApiResponse::message('Question archived.');
    }

    public function restore(string $id): JsonResponse
    {
        $question = Question::withTrashed()->findOrFail($id);
        $question->restore();
        $question->update(['is_active' => true]);

        return ApiResponse::message('Question restored.');
    }

    private function validateTypeRules(array $data): ?string
    {
        return match ($data['type']) {
            'mcq_single' => $this->validateSingleChoice($data),
            'true_false' => $this->validateTrueFalse($data),
            'mcq_multi' => $this->validateMultiChoice($data),
            'matching' => $this->validateMatching($data),
            default => null,
        };
    }

    private function validateSingleChoice(array $data): ?string
    {
        $correct = collect($data['options'] ?? [])->where('is_correct', true)->count();

        return $correct !== 1 ? 'MCQ single-choice must have exactly one correct option.' : null;
    }

    private function validateMultiChoice(array $data): ?string
    {
        $correct = collect($data['options'] ?? [])->where('is_correct', true)->count();

        return $correct < 1 ? 'MCQ multi-choice must have at least one correct option.' : null;
    }

    private function validateTrueFalse(array $data): ?string
    {
        if (count($data['options'] ?? []) !== 2) {
            return 'True/False must have exactly two options.';
        }
        $correct = collect($data['options'])->where('is_correct', true)->count();

        return $correct !== 1 ? 'True/False must have exactly one correct option.' : null;
    }

    private function validateMatching(array $data): ?string
    {
        $missingPair = collect($data['options'] ?? [])
            ->filter(fn ($o) => empty($o['match_pair']))
            ->isNotEmpty();

        return $missingPair ? 'All Matching options must include a match_pair value.' : null;
    }
}
