<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Exams\Actions\Questions\SuggestExamQuestions;
use App\Domains\Exams\Actions\Questions\SyncExamQuestions;
use App\Domains\Exams\Data\Input\SyncExamQuestionsData;
use App\Domains\Exams\Data\Output\ExamQuestionData;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamQuestionController extends Controller
{
    public function __construct(
        private SyncExamQuestions $syncQuestions,
        private SuggestExamQuestions $suggestQuestions,
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

     public function sync(SyncExamQuestionsData $data, Exam $exam, Request $request): JsonResponse
    {
        $this->authorize('manageQuestions', $exam);

        $synced = $this->syncQuestions->execute($exam, $data, $request->user('tenant')->id);

        return ApiResponse::success(
            ExamQuestionData::collect($synced),
            'Exam questions synced.'
        );
    }

    public function suggest(Request $request, Exam $exam): JsonResponse
    {
        $this->authorize('manageQuestions', $exam);

        $validated = $request->validate(['count' => ['required', 'integer', 'min:1']]);

        $suggestions = $this->suggestQuestions->execute($exam, $validated['count']);

        return ApiResponse::success($suggestions, 'Suggested questions retrieved.');
    }
}
