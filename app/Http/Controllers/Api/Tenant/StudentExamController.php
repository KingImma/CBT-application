<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ExamAnswerAction;
use App\Actions\Tenants\Exam\ExamSessionAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\ExamAttemptResource;
use App\Http\Resources\StudentExamQuestionResource;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentExamController extends Controller
{
    public function __construct(
        private ExamSessionAction $sessionAction,
        private ExamAnswerAction $answerAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);

        $exams = Exam::where('status', 'active')
            ->where(function ($q) use ($request) {
                $q->where('class_level_id', $request->user('tenant')->studentProfile?->class_level_id)
                    ->where(function ($q2) use ($request) {
                        $q2->whereNull('class_arm_id')
                            ->orWhere('class_arm_id', $request->user('tenant')->studentProfile?->class_arm_id);
                    });
            })
            ->with(['subject', 'classLevel'])
            ->paginate($perPage);

        return ApiResponse::paginated($exams, 'Available exams retrieved.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $exam = Exam::with(['subject', 'classLevel'])->findOrFail($id);

        $lastAttempt = ExamAttempt::forExam($exam->id)
            ->forStudent($request->user('tenant')->id)
            ->orderByDesc('attempt_number')
            ->first();

        return ApiResponse::success([
            'exam' => $exam,
            'last_attempt' => $lastAttempt,
        ], 'Exam details retrieved.');
    }

    public function start(Request $request, string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $student = $request->user('tenant');

        try {
            $this->sessionAction->validateStart($exam, $student);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        try {
            $attempt = $this->sessionAction->startAttempt($exam, $student);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505' || str_contains($e->getMessage(), 'idx_unique_in_progress_attempt')) {
                return ApiResponse::error('You already have an active exam attempt.', 422);
            }
            throw $e;
        }

        $questionsData = $this->sessionAction->getQuestions($attempt);

        return ApiResponse::created([
            'attempt' => $attempt,
            'questions' => StudentExamQuestionResource::collection($questionsData['questions']),
            'order' => $questionsData['order'],
        ], 'Exam started.');
    }

    public function activeAttempt(Request $request, string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $student = $request->user('tenant');

        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->forStudent($student->id)
            ->inProgress()
            ->first();

        if (! $attempt) {
            return ApiResponse::error('No active attempt found.', 404);
        }

        $data = $this->sessionAction->recover($attempt);
        $data['questions'] = StudentExamQuestionResource::collection($data['questions']);

        return ApiResponse::success($data, 'Active attempt retrieved.');
    }

    public function getQuestions(Request $request, string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $student = $request->user('tenant');

        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->forStudent($student->id)
            ->inProgress()
            ->first();

        if (! $attempt) {
            return ApiResponse::error('No active attempt found for this exam.', 404);
        }

        $questionsData = $this->sessionAction->getQuestions($attempt);

        return ApiResponse::success([
            'exam_id' => $exam->id,
            'attempt_id' => $attempt->id,
            'questions' => StudentExamQuestionResource::collection($questionsData['questions']),
            'order' => $questionsData['order'],
            'time_remaining_seconds' => $attempt->getTimeRemainingSeconds(),
        ], 'Questions retrieved.');
    }

    public function getAttemptQuestions(Request $request, string $id): JsonResponse
    {
        $attempt = ExamAttempt::with('exam')->findOrFail($id);
        $student = $request->user('tenant');

        if ($attempt->student_id !== $student->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        if ($attempt->status !== 'in_progress') {
            return ApiResponse::error('Only in-progress attempts can retrieve questions.', 422);
        }

        $questionsData = $this->sessionAction->getQuestions($attempt);

        return ApiResponse::success([
            'exam_id' => $attempt->exam_id,
            'attempt_id' => $attempt->id,
            'questions' => StudentExamQuestionResource::collection($questionsData['questions']),
            'order' => $questionsData['order'],
            'time_remaining_seconds' => $attempt->getTimeRemainingSeconds(),
        ], 'Questions retrieved.');
    }

    public function saveAnswer(Request $request, string $attemptId, string $questionId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $student = $request->user('tenant');

        $this->authorize('saveAnswer', $attempt);

        $validated = $request->validate([
            'selected_option_ids' => ['sometimes', 'array'],
            'text_answer' => ['sometimes', 'string'],
            'time_spent_seconds' => ['sometimes', 'integer', 'min:0'],
        ]);

        $answer = $this->answerAction->save($attempt, $questionId, $validated);

        return ApiResponse::success($answer, 'Answer saved.');
    }

    public function bulkSave(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('saveAnswer', $attempt);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'uuid'],
            'answers.*.selected_option_ids' => ['sometimes', 'array'],
            'answers.*.text_answer' => ['sometimes', 'string'],
            'answers.*.time_spent_seconds' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->answerAction->bulkSave($attempt, $validated['answers']);

        return ApiResponse::message('Answers saved.');
    }

    public function timeRemaining(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $student = $request->user('tenant');

        if ($attempt->student_id !== $student->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $remainingSeconds = $attempt->getTimeRemainingSeconds();

        return ApiResponse::success([
            'remaining_seconds' => $remainingSeconds,
            'expired' => $remainingSeconds <= 0,
        ], 'Time remaining retrieved.');
    }

    public function submit(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('submit', $attempt);

        try {
            $attempt = $this->sessionAction->submit($attempt);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $exam = $attempt->exam;
        $examSettings = $exam->settings;
        $canShowResult = false;
        $result = null;

        if ($examSettings->showResultImmediately && $attempt->status === 'graded') {
            $canShowResult = true;
            $result = $attempt;
        }

        return ApiResponse::success([
            'attempt' => $attempt,
            'can_show_result' => $canShowResult,
            'result' => $result,
        ], 'Exam submitted.');
    }

    public function toggleFlag(Request $request, string $attemptId, string $questionId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('saveAnswer', $attempt);

        $answer = ExamAnswer::where('attempt_id', $attemptId)
            ->where('question_id', $questionId)
            ->firstOrFail();

        $isFlagged = $this->answerAction->toggleFlag($answer);

        return ApiResponse::success(['is_flagged' => $isFlagged], 'Flag toggled.');
    }

    public function logSuspiciousEvent(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('saveAnswer', $attempt);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in([
                'tab_switch',
                'visibility_change',
                'fullscreen_exit',
                'copy_attempt',
                'paste_detected',
            ])],
            'metadata' => ['sometimes', 'array'],
        ]);

        $attempt->logSuspiciousEvent($validated['type'], $validated['metadata'] ?? []);

        return ApiResponse::message('Suspicious event logged.');
    }

    public function result(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::with('exam')->findOrFail($attemptId);
        $student = $request->user('tenant');

        if ($attempt->student_id !== $student->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $exam = $attempt->exam;
        $examSettings = $exam->settings;

        if ($examSettings->showResultImmediately && $attempt->status === 'graded') {
            return ApiResponse::success(new ExamAttemptResource($attempt), 'Result retrieved.');
        }

        $releaseDate = $examSettings->resultsReleaseDate;
        if ($releaseDate && now()->greaterThanOrEqualTo($releaseDate)) {
            return ApiResponse::success(new ExamAttemptResource($attempt), 'Result retrieved.');
        }

        if ($exam->status === 'published') {
            return ApiResponse::success(new ExamAttemptResource($attempt), 'Result retrieved.');
        }

        return ApiResponse::error('Results not yet released.', 403);
    }
}
