<?php

// - Manages individual options on an existing question post-creation
// - Handles label, content, image_url, match_pair, order — all schema columns
// - Chosen: separate controller so QuestionController stays focused on creation
// - Deliverable: add/edit/delete/reorder options without recreating the question
// - Alternative: embed option edits in question PATCH — works but creates fat payloads

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuestionOptionController extends Controller
{
    public function store(Request $request, string $questionId): JsonResponse
    {
        $question = Question::findOrFail($questionId);

        $validated = $request->validate([
            'label'      => ['nullable', 'string', 'max:10'],
            'content'    => ['required', 'string'],
            'image_url'  => ['nullable', 'url', 'max:500'],
            'is_correct' => ['required', 'boolean'],
            'order'      => ['nullable', 'integer'],
            'match_pair' => ['nullable', 'string', 'max:255'],
        ]);

        // Enforce single-correct for mcq_single
        if ($validated['is_correct'] && $question->type === 'mcq_single') {
            $question->options()->update(['is_correct' => false]);
        }

        $option = QuestionOption::create(array_merge($validated, [
            'question_id' => $questionId,
            'order'       => $validated['order'] ?? $question->options()->count(),
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Option added.',
            'data'    => $option,
        ], 201);
    }

    public function update(Request $request, string $questionId, string $id): JsonResponse
    {
        $question = Question::findOrFail($questionId);
        $option   = QuestionOption::where('question_id', $questionId)->findOrFail($id);

        $validated = $request->validate([
            'label'      => ['sometimes', 'nullable', 'string', 'max:10'],
            'content'    => ['sometimes', 'string'],
            'image_url'  => ['sometimes', 'nullable', 'url', 'max:500'],
            'is_correct' => ['sometimes', 'boolean'],
            'order'      => ['sometimes', 'integer'],
            'match_pair' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (($validated['is_correct'] ?? false) && $question->type === 'mcq_single') {
            $question->options()->where('id', '!=', $id)->update(['is_correct' => false]);
        }

        $option->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Option updated.',
            'data'    => $option->fresh(),
        ]);
    }

    public function destroy(string $questionId, string $id): JsonResponse
    {
        $option = QuestionOption::where('question_id', $questionId)->findOrFail($id);
        $option->delete();

        return response()->json(['success' => true, 'message' => 'Option removed.']);
    }

    public function reorder(Request $request, string $questionId): JsonResponse
    {
        // - Bulk updates order column by position in submitted ID array
        Question::findOrFail($questionId);

        $request->validate([
            'order'   => ['required', 'array', 'min:1'],
            'order.*' => ['uuid'],
        ]);

        foreach ($request->order as $position => $optionId) {
            QuestionOption::where('id', $optionId)
                ->where('question_id', $questionId)
                ->update(['order' => $position]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Options reordered.',
            'data'    => QuestionOption::where('question_id', $questionId)
                ->orderBy('order')->get(),
        ]);
    }
}