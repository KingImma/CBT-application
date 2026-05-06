<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\ValidateExamStartAction;
use App\Actions\Tenants\Exam\CreateExamAttemptAction;
use App\Actions\Tenants\Exam\GetExamQuestionsAction;
use App\Actions\Tenants\Exam\SaveAnswerAction;
use App\Actions\Tenants\Exam\BulkSaveAnswersAction;
use App\Actions\Tenants\Exam\GetTimeRemainingAction;
use App\Actions\Tenants\Exam\SubmitExamAction;
use App\Actions\Tenants\Exam\ToggleFlagAnswerAction;
use App\Actions\Tenants\Exam\LogSuspiciousEventAction;
use App\Actions\Tenants\Exam\RecoverExamAttemptAction;
use App\Models\Tenant\ExamAnswer;
use App\Events\StudentStartedExam;
use App\Events\StudentSubmittedExam;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentExamController extends Controller
{
    public function __construct(
        private ValidateExamStartAction $validateAction,
        private CreateExamAttemptAction $createAttemptAction,
        private GetExamQuestionsAction $getQuestionsAction,
        private SaveAnswerAction $saveAnswerAction,
        private BulkSaveAnswersAction $bulkSaveAction,
        private GetTimeRemainingAction $timeRemainingAction,
        private SubmitExamAction $submitAction,
        private ToggleFlagAnswerAction $toggleFlagAction,
        private LogSuspiciousEventAction $logSuspiciousAction,
        private RecoverExamAttemptAction $recoverAction,
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
        $exam = Exam::with(['subject', 'classLevel', 'topics'])->findOrFail($id);
        
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
            $this->validateAction->execute($exam, $student);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $attempt = $this->createAttemptAction->execute($exam, $student);
        $questionsData = $this->getQuestionsAction->execute($attempt);

        // Broadcast to teacher
        broadcast(new StudentStartedExam($attempt));

        return ApiResponse::created([
            'attempt' => $attempt,
            'questions' => $questionsData['questions'],
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

        $data = $this->recoverAction->execute($attempt);

        return ApiResponse::success($data, 'Active attempt retrieved.');
    }

    public function getQuestions(Request $request, string $id): JsonResponse
    {
        $attempt = ExamAttempt::with('exam')->findOrFail($id);
        $student = $request->user('tenant');
        
        if ($attempt->student_id !== $student->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $questionsData = $this->getQuestionsAction->execute($attempt);

        return ApiResponse::success($questionsData, 'Questions retrieved.');
    }

    public function saveAnswer(Request $request, string $attemptId, string $questionId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $student = $request->user('tenant');
        
        $this->authorize('saveAnswer', $attempt);

        $validated = $request->validate([
            'selected_option_ids' => ['sometimes', 'array'],
            'text_answer' => ['sometimes', 'string'],
            'ordering_answer' => ['sometimes', 'array'],
            'matching_answer' => ['sometimes', 'array'],
            'time_spent_seconds' => ['sometimes', 'integer', 'min:0'],
        ]);

        $answer = $this->saveAnswerAction->execute($attempt, $questionId, $validated);

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
            'answers.*.ordering_answer' => ['sometimes', 'array'],
            'answers.*.matching_answer' => ['sometimes', 'array'],
            'answers.*.time_spent_seconds' => ['sometimes', 'integer', 'min:0'],
        ]);

        $this->bulkSaveAction->execute($attempt, $validated['answers']);

        return ApiResponse::message('Answers saved.');
    }

    public function timeRemaining(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $student = $request->user('tenant');
        
        if ($attempt->student_id !== $student->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $data = $this->timeRemainingAction->execute($attempt);

        return ApiResponse::success($data, 'Time remaining retrieved.');
    }

    public function submit(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('submit', $attempt);

        try {
            $attempt = $this->submitAction->execute($attempt);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        // Broadcast to teacher
        broadcast(new StudentSubmittedExam($attempt));

        // Check if results can be shown
        $exam = $attempt->exam;
        $settings = $exam->settings ?? [];
        $canShowResult = false;
        $result = null;

        if (($settings['show_result_immediately'] ?? false) && $attempt->status === 'graded') {
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

        $isFlagged = $this->toggleFlagAction->execute($answer);

        return ApiResponse::success(['is_flagged' => $isFlagged], 'Flag toggled.');
    }

    public function logSuspiciousEvent(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('saveAnswer', $attempt);

        $validated = $request->validate([
            'type' => ['required', 'string'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $this->logSuspiciousAction->execute($attempt, $validated['type'], $validated['metadata'] ?? []);

        // Broadcast to teacher
        broadcast(new SuspiciousActivityDetected($attempt, $validated['type'], $validated['metadata'] ?? []));

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
        $settings = $exam->settings ?? [];

        // Check if results can be shown
        if (($settings['show_result_immediately'] ?? false) && $attempt->status === 'graded') {
            return ApiResponse::success($attempt, 'Result retrieved.');
        }

        // Check results release date
        $releaseDate = $settings['results_release_date'] ?? null;
        if ($releaseDate && now()->greaterThanOrEqualTo($releaseDate)) {
            return ApiResponse::success($attempt, 'Result retrieved.');
        }

        if ($exam->status === 'published') {
            return ApiResponse::success($attempt, 'Result retrieved.');
        }

        return ApiResponse::error('Results not yet released.', 403);
    }
}
