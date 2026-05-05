<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\AddQuestionToExamAction;
use App\Actions\Tenants\Exam\RemoveQuestionFromExamAction;
use App\Actions\Tenants\Exam\ReorderExamQuestionsAction;
use App\Actions\Tenants\Exam\AutoGenerateQuestionsAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamQuestionController extends Controller
{
    public function __construct(
        private AddQuestionToExamAction $addAction,
        private RemoveQuestionFromExamAction $removeAction,
        private ReorderExamQuestionsAction $reorderAction,
        private AutoGenerateQuestionsAction $autoGenerateAction,
    ) {}

    public function store(Request $request, string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $validated = $request->validate([
            'question_id' => ['required', 'uuid', 'exists:questions,id'],
            'marks_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $examQuestion = $this->addAction->execute($exam, $validated['question_id'], $validated['marks_override'] ?? null);

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
            'rules.*.difficulty' => ['nullable', 'in:easy,medium,hard'],
            'rules.*.count' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $this->autoGenerateAction->execute($exam, $validated['rules']);
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

    public function destroy(string $examId, string $questionId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('manageQuestions', $exam);

        $this->removeAction->execute($exam, $questionId);

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

        $this->reorderAction->execute($exam, $validated['order']);

        return ApiResponse::message('Questions reordered.');
    }
}
