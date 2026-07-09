<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\Questions\AddExamQuestion;
use App\Actions\Tenants\Exam\Questions\DeleteExamQuestion;
use App\Actions\Tenants\Exam\Questions\RandomizeExamQuestions;
use App\Actions\Tenants\Exam\Questions\ReorderExamQuestions;
use App\Actions\Tenants\Exam\Questions\UpdateExamQuestion;
use App\Models\Tenant\ExamQuestion;
use App\Data\Exam\Input\AddQuestionData;
use App\Data\Exam\Input\RandomizeQuestionsData;
use App\Data\Exam\Input\ReorderQuestionsData;
use App\Data\Exam\Input\UpdateExamQuestionData;
use App\Data\Exam\Output\ExamQuestionData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamQuestionController extends Controller
{
    public function __construct(
        private AddExamQuestion $addQuestion,
        private UpdateExamQuestion $updateQuestion,
        private DeleteExamQuestion $deleteQuestion,
        private ReorderExamQuestions $reorderQuestions,
        private RandomizeExamQuestions $randomizeQuestions,
    ) {}

    public function index(Exam $exam): JsonResponse
    {
        $this->authorize('view', $exam);

        return ApiResponse::success(
            ExamQuestionData::collect(
                $exam->examQuestions()->with('question.options')->orderBy('order')->get()
            ),
            'Exam questions retrieved.'
        );
    }

    public function store(AddQuestionData $data, Exam $exam, Request $request): JsonResponse
    {
        $this->authorize('manageQuestions', $exam);

        $examQuestion = $this->addQuestion->execute(
            $exam,
            Question::findOrFail($data->question_id),
            $data,
            $request->user('tenant')->id
        );

        return ApiResponse::created(
            $examQuestion->load('question'),
            'Question added to exam.'
        );
    }

    public function update(UpdateExamQuestionData $data, Exam $exam, string $examQuestion): JsonResponse
    {
        $this->authorize('manageQuestions', $exam);

        $eq = $this->resolveExamQuestion($exam, $examQuestion);

        return ApiResponse::success(
            $this->updateQuestion->execute($exam, $eq->question, $data)->load('question'),
            'Exam question updated.'
        );
    }

    public function destroy(Exam $exam, string $examQuestion): JsonResponse
    {
        $this->authorize('manageQuestions', $exam);

        $eq = $this->resolveExamQuestion($exam, $examQuestion);

        $this->deleteQuestion->execute($exam, $eq->question);

        return ApiResponse::message('Question removed from exam.');
    }

    public function reorder(ReorderQuestionsData $data, Exam $exam): JsonResponse
    {
        $this->authorize('manageQuestions', $exam);

        $this->reorderQuestions->execute($exam, $data);

        return ApiResponse::message('Questions reordered.');
    }

    public function randomize(RandomizeQuestionsData $data, Exam $exam): JsonResponse
    {
        $this->authorize('manageQuestions', $exam);

        $this->randomizeQuestions->execute($exam, $data->count);

        $exam->refresh();

        return ApiResponse::success([
            'total_marks' => $exam->total_marks,
            'questions' => $exam->examQuestions()->with('question.options')->orderBy('order')->get(),
        ], 'Questions randomized.');
    }

    private function resolveExamQuestion(Exam $exam, string $identifier): ExamQuestion
    {
        return $exam->examQuestions()->where('id', $identifier)->first()
            ?? $exam->examQuestions()->where('question_id', $identifier)->firstOrFail();
    }
}
