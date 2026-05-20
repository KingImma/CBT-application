<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ExamGradingAction;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Exam Administration
 * * APIs for scheduling CBT sessions, attaching questions, live monitoring, and grading.
 */
class ExamGradingController extends Controller
{
    public function __construct(
        private ExamGradingAction $gradingAction,
    ) {}

    public function ungradedAttempts(string $examId): JsonResponse
    {
        $exam = Exam::findOrFail($examId);
        $this->authorize('viewMonitoring', $exam);

        $attempts = ExamAttempt::forExam($examId)
            ->needsGrading()
            ->with('student:id,first_name,last_name')
            ->whereHas('answers', fn ($q) => $q
                ->whereHas('question', fn ($q) => $q->where('type', 'essay'))
                ->whereNull('marks_awarded')
            )
            ->get();

        return ApiResponse::success($attempts, 'Ungraded attempts retrieved.');
    }

    public function theoryAnswers(string $examId, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::with('student:id,first_name,last_name')->findOrFail($attemptId);
        $this->authorize('grade', $attempt);

        $answers = ExamAnswer::where('attempt_id', $attemptId)
            ->whereHas('question', fn ($q) => $q->whereIn('type', ['essay', 'short_answer']))
            ->with('question', 'question.options', 'question.fillBlankAnswers')
            ->get();

        return ApiResponse::success([
            'attempt' => $attempt,
            'answers' => $answers,
        ], 'Theory answers retrieved.');
    }

    public function gradeAnswer(Request $request, string $answerId): JsonResponse
    {
        $answer = ExamAnswer::with('attempt')->findOrFail($answerId);
        $this->authorize('update', $answer);

        $validated = $request->validate([
            'marks' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string'],
        ]);

        $answer = $this->gradingAction->gradeTheory(
            $answer,
            (float) $validated['marks'],
            $validated['feedback'] ?? '',
            $request->user('tenant')
        );

        return ApiResponse::success($answer, 'Answer graded.');
    }

    public function markFullyGraded(string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('grade', $attempt);

        try {
            $attempt = $this->gradingAction->markFullyGraded($attempt);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($attempt, 'Attempt marked as fully graded.');
    }

    public function recomputeScore(string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('grade', $attempt);

        $attempt = $this->gradingAction->recomputeScore($attempt);

        return ApiResponse::success($attempt, 'Score recomputed.');
    }
}
