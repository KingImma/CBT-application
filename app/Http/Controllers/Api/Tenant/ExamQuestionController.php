<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ExamQuestionAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
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
        private ExamQuestionAction $questionAction,
    ) {}

    public function store(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $validated = $request->validate([
            'question_id' => ['required', 'uuid', 'exists:questions,id'],
            'marks_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $examQuestion = $this->questionAction->add($exam, $validated['question_id'], $validated['marks_override'] ?? null, auth()->id());
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

    public function autoGenerate(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $validated = $request->validate([
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.type' => ['nullable', 'in:mcq_single,mcq_multi,true_false,fill_blank,short_answer,essay,matching,ordering'],
            'rules.*.count' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->questionAction->autoGenerate($exam, $validated['rules']);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $exam->refresh();
        $questions = $exam->examQuestions()->with('question.options', 'question.topic')->get();

        return ApiResponse::success(
            ['total_marks' => $exam->total_marks, 'questions' => $questions],
            'Questions auto-generated successfully.'
        );
    }

    public function update(Request $request, string $examId, string $questionId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $validated = $request->validate([
            'marks_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $examQuestion = $this->questionAction->updateMarks($exam, $questionId, $validated['marks_override'] ?? null);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            $examQuestion->load('question'),
            'Question marks updated.'
        );
    }

    public function destroy(string $examId, string $questionId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $this->questionAction->remove($exam, $questionId);

        return ApiResponse::message('Question removed from exam.');
    }

    public function reorder(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'min:1'],
        ]);

        $this->questionAction->reorder($exam, $validated['order']);

        return ApiResponse::message('Questions reordered.');
    }
}
