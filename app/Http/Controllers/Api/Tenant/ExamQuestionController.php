<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ManageExamQuestion;
use App\Actions\Tenants\Exam\RandomizeExamQuestions;
use App\Data\Exam\ExamQuestionData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Exam Administration
 * * APIs for scheduling CBT sessions, attaching questions, live monitoring, and grading.
 */
class ExamQuestionController extends Controller
{
    public function __construct(
        private ManageExamQuestion $managementAction,
        private RandomizeExamQuestions $randomizationAction,
    ) {}

    /**
     * List questions attached to an exam.
     *
     * @subgroup Exam Questions
     *
     * @urlParam examId string required The exam UUID.
     */
    public function index(string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('view', $exam);

        $questions = ExamQuestion::where('exam_id', $exam->id)
            ->with('question.options')
            ->orderBy('order')
            ->get();

        return ApiResponse::success(
            ExamQuestionData::collect($questions),
            'Exam questions retrieved.',
        );
    }

    /**
     * Attach a question to an exam.
     *
     * @subgroup Exam Questions
     *
     * @urlParam examId string required The exam UUID.
     *
     * @bodyParam question_id string required The question UUID. No-example
     * @bodyParam marks_override numeric nullable Override the default marks for this question. No-example
     */
    public function store(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $validated = $request->validate([
            'question_id' => ['required', 'uuid', 'exists:questions,id'],
            'marks_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $examQuestion = $this->managementAction->add($exam, $validated['question_id'], $validated['marks_override'] ?? null, auth()->id());
        } catch (QueryException $e) {
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                return ApiResponse::error('This question has already been added to the exam.', 422);
            }
            throw $e;
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created(
            $examQuestion->load('question'),
            'Question added to exam.'
        );
    }

    /**
     * Randomly select questions from the question bank for this exam.
     *
     * @subgroup Exam Questions
     *
     * @urlParam examId string required The exam UUID.
     *
     * @bodyParam count int required Number of questions to randomly select. Example: 10
     */
    public function randomize(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->randomizationAction->execute($exam, $validated['count']);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $exam->refresh();
        $questions = $exam->examQuestions()->with('question.options')->get();

        return ApiResponse::success(
            ['total_marks' => $exam->total_marks, 'questions' => $questions],
            'Questions randomized successfully.'
        );
    }

    /**
     * Update marks override for a question in an exam.
     *
     * @subgroup Exam Questions
     *
     * @urlParam examId string required The exam UUID.
     * @urlParam questionId string required The question UUID.
     *
     * @bodyParam marks_override numeric nullable Override marks. No-example
     */
    public function update(Request $request, string $examId, string $questionId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $validated = $request->validate([
            'marks_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $examQuestion = $this->managementAction->updateMarks($exam, $questionId, $validated['marks_override'] ?? null);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $examQuestion->load('question'),
            'Question marks updated.'
        );
    }

    /**
     * Remove a question from an exam.
     *
     * @subgroup Exam Questions
     *
     * @urlParam examId string required The exam UUID.
     * @urlParam questionId string required The question UUID.
     */
    public function destroy(string $examId, string $questionId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $this->managementAction->remove($exam, $questionId);

        return ApiResponse::message('Question removed from exam.');
    }

    /**
     * Reorder questions in an exam.
     *
     * @subgroup Exam Questions
     *
     * @urlParam examId string required The exam UUID.
     *
     * @bodyParam order array required Array of question orders (position => question_index). No-example
     */
    public function reorder(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'min:1'],
        ]);

        $this->managementAction->reorder($exam, $validated['order']);

        return ApiResponse::message('Questions reordered.');
    }
}
