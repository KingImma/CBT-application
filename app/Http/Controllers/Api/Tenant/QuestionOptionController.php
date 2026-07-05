<?php

// - Manages individual options on an existing question post-creation
// - Handles label, content, image_url, match_pair, order — all schema columns
// - Chosen: separate controller so QuestionController stays focused on creation
// - Deliverable: add/edit/delete/reorder options without recreating the question
// - Alternative: embed option edits in question PATCH — works but creates fat payloads

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Data\Question\QuestionOptionData;
use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Question Bank
 * * APIs for creating and managing objective and theory questions.
 */
class QuestionOptionController extends Controller
{
    /**
     * Add an option to a question.
     *
     * @subgroup Question Options
     *
     * @urlParam questionId string required The question UUID.
     *
     * @bodyParam label string nullable Short label (max 10 chars). Example: "A"
     * @bodyParam content string required Option text. Example: "Lagos"
     * @bodyParam image_url string nullable Image URL. No-example
     * @bodyParam is_correct boolean required Whether this is the correct option. No-example
     * @bodyParam order int nullable Display order. No-example
     * @bodyParam match_pair string nullable Matching pair text for matching questions. No-example
     */
    public function store(Request $request, string $questionId): JsonResponse
    {
        $question = Question::findOrFail($questionId);

        $isFitb = $question->type === QuestionType::FillInBlank->value;

        if ($isFitb) {
            $validated = $request->validate([
                'label' => ['nullable', 'string', 'max:10'],
                'content' => ['required', 'string'],
                'image_url' => ['nullable', 'url', 'max:500'],
                'is_correct' => ['prohibited'],
                'order' => ['nullable', 'integer'],
                'match_pair' => ['nullable', 'string', 'max:255'],
            ]);

            $option = QuestionOption::create(
                array_merge($validated, [
                    'question_id' => $questionId,
                    'is_correct' => true, // All FITB options are acceptable answers by definition
                    'order' => $validated['order'] ?? $question->options()->count(),
                ]),
            );
        } else {
            $validated = $request->validate([
                'label' => ['nullable', 'string', 'max:10'],
                'content' => ['required', 'string'],
                'image_url' => ['nullable', 'url', 'max:500'],
                'is_correct' => ['required', 'boolean'],
                'order' => ['nullable', 'integer'],
                'match_pair' => ['nullable', 'string', 'max:255'],
            ]);

            // Enforce single-correct for types that only allow one correct option.
            $questionType = QuestionType::tryFrom($question->type);
            if (
                $validated['is_correct'] &&
                $questionType?->maxCorrectOptions() === 1
            ) {
                $question->options()->update(['is_correct' => false]);
            }

            $option = QuestionOption::create(
                array_merge($validated, [
                    'question_id' => $questionId,
                    'order' => $validated['order'] ?? $question->options()->count(),
                ]),
            );
        }

        return ApiResponse::created(
            QuestionOptionData::from($option),
            'Option added.',
        );
    }

    /**
     * Update a question option.
     *
     * @subgroup Question Options
     *
     * @urlParam questionId string required The question UUID.
     * @urlParam id string required The option UUID.
     *
     * @bodyParam label string nullable Short label. No-example
     * @bodyParam content string Option text. No-example
     * @bodyParam image_url string nullable Image URL. No-example
     * @bodyParam is_correct boolean Whether correct. No-example
     * @bodyParam order int Display order. No-example
     * @bodyParam match_pair string nullable Match pair text. No-example
     */
    public function update(
        Request $request,
        string $questionId,
        string $id,
    ): JsonResponse {
        $question = Question::findOrFail($questionId);
        $option = QuestionOption::where('question_id', $questionId)->findOrFail(
            $id,
        );

        $isFitb = $question->type === QuestionType::FillInBlank->value;

        if ($isFitb) {
            $validated = $request->validate([
                'label' => ['sometimes', 'nullable', 'string', 'max:10'],
                'content' => ['sometimes', 'string'],
                'image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
                'is_correct' => ['prohibited'],
                'order' => ['sometimes', 'integer'],
                'match_pair' => ['sometimes', 'nullable', 'string', 'max:255'],
            ]);

            // FITB options are always acceptable answers; is_correct forced to true
            $option->update(array_merge($validated, ['is_correct' => true]));
        } else {
            $validated = $request->validate([
                'label' => ['sometimes', 'nullable', 'string', 'max:10'],
                'content' => ['sometimes', 'string'],
                'image_url' => ['sometimes', 'nullable', 'url', 'max:500'],
                'is_correct' => ['sometimes', 'boolean'],
                'order' => ['sometimes', 'integer'],
                'match_pair' => ['sometimes', 'nullable', 'string', 'max:255'],
            ]);

            $questionType = QuestionType::tryFrom($question->type);
            if (
                ($validated['is_correct'] ?? false) &&
                $questionType?->maxCorrectOptions() === 1
            ) {
                $question
                    ->options()
                    ->where('id', '!=', $id)
                    ->update(['is_correct' => false]);
            }

            $option->update($validated);
        }

        return ApiResponse::success(
            QuestionOptionData::from($option->fresh()),
            'Option updated.',
        );
    }

    /**
     * Delete a question option.
     *
     * @subgroup Question Options
     *
     * @urlParam questionId string required The question UUID.
     * @urlParam id string required The option UUID.
     */
    public function destroy(string $questionId, string $id): JsonResponse
    {
        $option = QuestionOption::where('question_id', $questionId)->findOrFail(
            $id,
        );
        $option->delete();

        return ApiResponse::message('Option removed.');
    }

    /**
     * Reorder options for a question.
     *
     * @subgroup Question Options
     *
     * @urlParam questionId string required The question UUID.
     *
     * @bodyParam order array required Array of option UUIDs in the desired order. No-example
     * @bodyParam order.* string Option UUID. No-example
     */
    public function reorder(Request $request, string $questionId): JsonResponse
    {
        $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['uuid'],
        ]);

        // Verify all option IDs belong to the question
        $question = Question::findOrFail($questionId);
        $validIds = $question->options()->pluck('id')->toArray();
        $invalidIds = array_diff($request->order, $validIds);

        if ($invalidIds !== []) {
            return ApiResponse::error(
                'Some options do not belong to this question.',
                422,
            );
        }

        foreach ($request->order as $position => $optionId) {
            QuestionOption::where('id', $optionId)
                ->where('question_id', $questionId)
                ->update(['order' => $position]);
        }

        return ApiResponse::success(
            QuestionOption::where('question_id', $questionId)
                ->orderBy('order')
                ->get(),
            'Options reordered.',
        );
    }
}
