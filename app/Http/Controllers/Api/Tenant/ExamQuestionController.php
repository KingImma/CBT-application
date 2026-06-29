<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\Questions\AddExamQuestion;
use App\Actions\Tenants\Exam\Questions\DeleteExamQuestion;
use App\Actions\Tenants\Exam\Questions\RandomizeExamQuestions;
use App\Actions\Tenants\Exam\Questions\ReorderExamQuestions;
use App\Actions\Tenants\Exam\Questions\UpdateExamQuestion;
use App\Data\Exam\Input\AddQuestionData;
use App\Data\Exam\Input\RandomizeQuestionsData;
use App\Data\Exam\Input\ReorderQuestionsData;
use App\Data\Exam\Input\UpdateExamQuestionData;
use App\Data\Exam\Output\ExamQuestionData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Exam Administration
 * * APIs for scheduling CBT sessions, attaching questions, live monitoring, and grading.
 */
class ExamQuestionController extends Controller
{
    /**
     * List questions attached to an exam.
     *
     * @subgroup Exam Questions
     */
    public function index(Exam $exam): JsonResponse
    {
        $this->authorize('view', $exam);

        $questions = $exam->examQuestions()
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
     */
    public function store(AddQuestionData $data, Exam $exam, AddExamQuestion $action, Request $request): JsonResponse
    {
        $this->authorize('manageQuestions', $exam);

        $question = Question::findOrFail($data->question_id);

        $examQuestion = $action->execute($exam, $question, $data, $request->user('tenant')->id);

        return ApiResponse::created(
            $examQuestion->load('question'),
            'Question added to exam.'
        );
    }

    /**
     * Randomly select questions from the question bank for this exam.
     *
     * @subgroup Exam Questions
     */
    public function randomize(RandomizeQuestionsData $data, Exam $exam, RandomizeExamQuestions $action): JsonResponse
    {
        $this->authorize('manageQuestions', $exam);

        $action->execute($exam, $data->count);

        return ApiResponse::success(
            [
                'total_marks' => $exam->fresh()->total_marks,
                'questions' => $exam->examQuestions()->with('question.options')->get(),
            ],
            'Questions randomized successfully.'
        );
    }

    /**
     * Update marks override for a question in an exam.
     *
     * @subgroup Exam Questions
     */
    public function update(
        UpdateExamQuestionData $data,
        Exam $exam,
        ExamQuestion $examQuestion,  // FIX: was Question $question — model binding resolved wrong model
        UpdateExamQuestion $action,
    ): JsonResponse {
        $this->authorize('manageQuestions', $exam);

        abort_if($examQuestion->exam_id !== $exam->id, 403, 'ExamQuestion does not belong to this exam.');

        $examQuestion = $action->execute($exam, $examQuestion, $data);

        return ApiResponse::success(
            $examQuestion->load('question'),
            'Exam Question updated.'
        );
    }

    /**
     * Remove a question from an exam.
     *
     * @subgroup Exam Questions
     */
    public function destroy(
        Exam $exam,
        ExamQuestion $examQuestion,  // FIX: was Question $question — model binding resolved wrong model
        DeleteExamQuestion $action,
    ): JsonResponse {
        $this->authorize('manageQuestions', $exam);

        $action->execute($exam, $examQuestion);

        return ApiResponse::message('Question removed from exam.');
    }

    /**
     * Reorder questions in an exam.
     *
     * @subgroup Exam Questions
     */
    public function reorder(
        ReorderQuestionsData $data,
        Exam $exam,
        ReorderExamQuestions $action,
    ): JsonResponse {
        $this->authorize('manageQuestions', $exam);

        $action->execute($exam, $data);

        return ApiResponse::message('Questions reordered.');
    }
}
