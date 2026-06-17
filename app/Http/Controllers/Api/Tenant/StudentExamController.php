<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\FinalizeAttempt;
use App\Actions\Tenants\Exam\ManageExamSession;
use App\Actions\Tenants\Exam\RecordExamAnswer;
use App\Data\Exam\ExamAttemptData;
use App\Data\Exam\StudentExamQuestionData;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\SuspiciousEventType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Support\ApiResponse;
use App\Support\Exam\ExamSessionStateStore;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @group Student Portal
 *
 * APIs for students to access and take exams.
 */
class StudentExamController extends Controller
{
    public function __construct(
        private ManageExamSession $sessionAction,
        private RecordExamAnswer $answerAction,
        private FinalizeAttempt $finalizeAttempt,
        private ExamSessionStateStore $stateStore,
    ) {}

    /**
     * List available exams for the authenticated student.
     *
     * @subgroup Available Exams
     *
     * @queryParam per_page int Results per page (default: 20). No-example
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);

        $exams = Exam::where('status', ExamStatus::Active->value)
            ->where(function ($q) use ($request) {
                $q->where(
                    'class_level_id',
                    $request->user('tenant')->studentProfile?->class_level_id,
                )->where(function ($q2) use ($request) {
                    $q2->whereNull('class_arm_id')->orWhere(
                        'class_arm_id',
                        $request->user('tenant')->studentProfile?->class_arm_id,
                    );
                });
            })
            ->with(['subject', 'classLevel'])
            ->paginate($perPage);

        return ApiResponse::paginated($exams, 'Available exams retrieved.');
    }

    /**
     * List the authenticated student's published results.
     *
     * @subgroup Results
     *
     * @queryParam exam_id string Filter by exam UUID. No-example
     * @queryParam per_page int Results per page (default: 20). No-example
     */
    public function results(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => ['sometimes', 'uuid', 'exists:exams,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $student = $request->user('tenant');

        $attempts = ExamAttempt::with([
            'exam.subject',
            'exam.classLevel',
            'exam.examQuestions',
            'answers.question.options',
        ])
            ->where('student_id', $student->id)
            ->whereIn('status', [
                ExamAttemptStatus::Graded->value,
                ExamAttemptStatus::Disqualified->value,
                ExamAttemptStatus::Timed_out->value,
            ])
            ->whereHas('exam', function ($query) {
                $query->whereNotNull('published_at');
            })
            ->when(
                isset($validated['exam_id']),
                fn ($query) => $query->where('exam_id', $validated['exam_id'])
            )
            ->latest('submitted_at')
            ->paginate($perPage);

        $results = $attempts->getCollection()->map(function ($attempt) {
            $questions = $attempt->answers->map(function ($answer) use ($attempt) {
                $question = $answer->question;
                $examQuestion = $attempt->exam->examQuestions
                    ->firstWhere('question_id', $question->id);

                $optionsMap = $question->options->keyBy('id');

                $selectedOptions = collect($answer->selected_option_ids ?? [])
                    ->map(fn ($optionId) => $optionsMap->has($optionId) ? [
                        'id' => $optionsMap[$optionId]->id,
                        'label' => $optionsMap[$optionId]->label,
                        'content' => $optionsMap[$optionId]->content,
                        'image_url' => $optionsMap[$optionId]->image_url,
                        'is_correct' => (bool) $optionsMap[$optionId]->is_correct,
                    ] : null)
                    ->filter()
                    ->values()
                    ->toArray();

                return [
                    'question_id' => $question->id,
                    'content' => $question->content,
                    'image_url' => $question->image_url,
                    'marks_available' => (float) ($examQuestion?->getEffectiveMarks() ?? $question->default_marks),
                    'marks_awarded' => (float) ($answer->marks_awarded ?? 0),
                    'is_correct' => (bool) $answer->is_correct,
                    'selected_options' => $selectedOptions,
                    'options' => $question->options->map(fn ($opt) => [
                        'id' => $opt->id,
                        'label' => $opt->label,
                        'content' => $opt->content,
                        'image_url' => $opt->image_url,
                        'is_correct' => (bool) $opt->is_correct, // ← correct answer revealed
                    ])->toArray(),
                ];
            })->toArray();

            return [
                'attempt_id' => $attempt->id,
                'exam_id' => $attempt->exam_id,
                'exam_title' => $attempt->exam->title,
                'exam_subject' => $attempt->exam->subject?->name,
                'status' => $attempt->status,
                'attempt_number' => $attempt->attempt_number,
                'total_score' => (float) $attempt->total_score,
                'total_marks' => (float) $attempt->exam->total_marks,
                'percentage_score' => (float) $attempt->percentage_score,
                'grade' => $attempt->grade,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'time_spent_seconds' => $attempt->time_spent_seconds,
                'questions' => $questions,
            ];
        });

        return ApiResponse::paginated(
            $attempts,
            'Results retrieved successfully.',
            $results,
        );
    }

    /**
     * Get details for a specific exam.
     *
     * @subgroup Available Exams
     *
     * @urlParam id string required The exam UUID.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $exam = Exam::with(['subject', 'classLevel'])->findOrFail($id);

        $lastAttempt = ExamAttempt::forExam($exam->id)
            ->forStudent($request->user('tenant')->id)
            ->orderByDesc('attempt_number')
            ->first();

        return ApiResponse::success(
            [
                'exam' => $exam,
                'last_attempt' => $lastAttempt,
            ],
            'Exam details retrieved.',
        );
    }

    /**
     * Start a new exam attempt.
     *
     * @subgroup Exam Attempts
     *
     * @urlParam id string required The exam UUID.
     */
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
            $isDuplicateAttempt = $e->getCode() === '23505' ||
                str_contains($e->getMessage(), 'idx_unique_in_progress_attempt');

            if (! $isDuplicateAttempt) {
                throw $e;
            }

            return ApiResponse::error(
                'You already have an active exam attempt.',
                422,
            );
        }

        $questionsData = $this->sessionAction->getQuestions($attempt);

        return ApiResponse::created(
            [
                'attempt' => $attempt,
                'questions' => StudentExamQuestionData::collect(
                    $questionsData['questions'],
                ),
                'order' => $questionsData['order'],
            ],
            'Exam started.',
        );
    }

    /**
     * Get the student's active attempt for an exam.
     *
     * @subgroup Exam Attempts
     *
     * @urlParam id string required The exam UUID.
     */
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
        $data['questions'] = StudentExamQuestionData::collect(
            $data['questions'],
        );

        return ApiResponse::success($data, 'Active attempt retrieved.');
    }

    /**
     * Get questions for an active exam attempt.
     *
     * @subgroup Answering
     *
     * @urlParam id string required The exam UUID.
     */
    public function getQuestions(Request $request, string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $student = $request->user('tenant');

        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->forStudent($student->id)
            ->inProgress()
            ->first();

        if (! $attempt) {
            return ApiResponse::error(
                'No active attempt found for this exam.',
                404,
            );
        }

        $questionsData = $this->sessionAction->getQuestions($attempt);

        return ApiResponse::success(
            [
                'exam_id' => $exam->id,
                'attempt_id' => $attempt->id,
                'questions' => StudentExamQuestionData::collect(
                    $questionsData['questions'],
                ),
                'order' => $questionsData['order'],
                'time_remaining_seconds' => $attempt->getTimeRemainingSeconds(),
            ],
            'Questions retrieved.',
        );
    }

    /**
     * Get questions for a specific attempt.
     *
     * @subgroup Answering
     *
     * @urlParam id string required The attempt UUID.
     */
    public function getAttemptQuestions(
        Request $request,
        string $id,
    ): JsonResponse {
        $attempt = ExamAttempt::with('exam')->findOrFail($id);
        $student = $request->user('tenant');

        if ($attempt->student_id !== $student->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
            return ApiResponse::error(
                'Only in-progress attempts can retrieve questions.',
                422,
            );
        }

        $questionsData = $this->sessionAction->getQuestions($attempt);

        return ApiResponse::success(
            [
                'exam_id' => $attempt->exam_id,
                'attempt_id' => $attempt->id,
                'questions' => StudentExamQuestionData::collect(
                    $questionsData['questions'],
                ),
                'order' => $questionsData['order'],
                'time_remaining_seconds' => $attempt->getTimeRemainingSeconds(),
            ],
            'Questions retrieved.',
        );
    }

    /**
     * Save an answer for a question in an attempt.
     *
     * @subgroup Answering
     *
     * @urlParam attemptId string required The attempt UUID.
     * @urlParam questionId string required The question UUID.
     *
     * @bodyParam selected_option_ids array Selected option UUIDs for MCQ questions. No-example
     * @bodyParam text_answer string Text answer for theory questions. No-example
     * @bodyParam time_spent_seconds int Time spent on this question. No-example
     */
    public function saveAnswer(
        Request $request,
        string $attemptId,
        string $questionId,
    ): JsonResponse {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('saveAnswer', $attempt);

        $validated = $request->validate([
            'selected_option_ids' => ['sometimes', 'array'],
            'text_answer' => ['sometimes', 'string'],
            'time_spent_seconds' => ['sometimes', 'integer', 'min:0'],
        ]);

        $answer = $this->answerAction->save($attempt, $questionId, $validated);

        return ApiResponse::success($answer, 'Answer saved.');
    }

    /**
     * Bulk save answers for an attempt.
     *
     * @subgroup Answering
     *
     * @urlParam attemptId string required The attempt UUID.
     *
     * @bodyParam answers array required Array of answers. No-example
     * @bodyParam answers.*.question_id string required The question UUID. No-example
     * @bodyParam answers.*.selected_option_ids array Selected option UUIDs. No-example
     * @bodyParam answers.*.text_answer string Text answer. No-example
     * @bodyParam answers.*.time_spent_seconds int Time spent on this question. No-example
     */
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

    /**
     * Get remaining time for an attempt.
     *
     * @subgroup Exam Progress
     *
     * @urlParam attemptId string required The attempt UUID.
     */
    public function timeRemaining(
        Request $request,
        string $attemptId,
    ): JsonResponse {
        $attempt = ExamAttempt::findOrFail($attemptId);

        if ($attempt->student_id !== $request->user('tenant')->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $remaining = $attempt->getTimeRemainingSeconds();

        return ApiResponse::success(
            [
                'remaining_seconds' => $remaining,
                'expired' => $remaining <= 0,
            ],
            'Time remaining retrieved.',
        );
    }

    /**
     * Submit an exam attempt for grading.
     *
     * @subgroup Exam Attempts
     *
     * @urlParam attemptId string required The attempt UUID.
     */
    public function submit(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('submit', $attempt);

        if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
            return ApiResponse::error('Already submitted.', 409);
        }

        try {
            $attempt = $this->finalizeAttempt->execute(
                $attempt,
                $request->user('tenant'),
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            ['attempt_id' => $attempt->id],
            'Exam submitted for grading.',
            202,
        );
    }

    /**
     * Toggle a flag on a question for review.
     *
     * @subgroup Answering
     *
     * @urlParam attemptId string required The attempt UUID.
     * @urlParam questionId string required The question UUID.
     */
    public function toggleFlag(
        Request $request,
        string $attemptId,
        string $questionId,
    ): JsonResponse {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('saveAnswer', $attempt);

        $answer = ExamAnswer::where('attempt_id', $attemptId)
            ->where('question_id', $questionId)
            ->firstOrFail();

        $isFlagged = $this->answerAction->toggleFlag($answer);

        return ApiResponse::success(
            ['is_flagged' => $isFlagged],
            'Flag toggled.',
        );
    }

    /**
     * Log a suspicious event during an exam.
     *
     * @subgroup Integrity
     *
     * @urlParam attemptId string required The attempt UUID.
     *
     * @bodyParam type string required Event type. Must be one of: tab_switch,
     * visibility_change, fullscreen_exit, copy_attempt, paste_detected. Example: "tab_switch"
     * @bodyParam metadata array Additional event metadata. No-example
     */
    public function logSuspiciousEvent(
        Request $request,
        string $attemptId,
    ): JsonResponse {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('saveAnswer', $attempt);

        $validated = $request->validate([
            'type' => [
                'required',
                'string',
                Rule::in(array_column(SuspiciousEventType::cases(), 'value')),
            ],
            'metadata' => ['sometimes', 'array'],
        ]);

        $attempt->logSuspiciousEvent(
            SuspiciousEventType::from($validated['type']),
            $validated['metadata'] ?? [],
        );
        $attempt->save();

        return ApiResponse::message('Suspicious event logged.');
    }

    /**
     * Get the result for a completed exam attempt.
     *
     * @subgroup Results
     *
     * @urlParam attemptId string required The attempt UUID.
     */
    public function result(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::with('exam')->findOrFail($attemptId);
        $student = $request->user('tenant');

        if ($attempt->student_id !== $student->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $exam = $attempt->exam;

        if (! $exam->isPublished()) {
            return ApiResponse::error(
                'Results for this exam have not been released yet.',
                403,
            );
        }

        return ApiResponse::success(
            ExamAttemptData::from($attempt),
            'Result retrieved.',
        );
    }

    /**
     * Get the current session state for reconnection.
     *
     * @subgroup Exam Attempts
     *
     * @urlParam attemptId string required The attempt UUID.
     */
    public function sessionState(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $student = $request->user('tenant');

        if ($attempt->student_id !== $student->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $tenantId = (string) tenant('id');
        $cached = $this->stateStore->read($tenantId, $attemptId);

        if ($cached !== null) {
            return ApiResponse::success([
                'attempt_id' => $cached->attemptId,
                'time_remaining_seconds' => $cached->timeRemainingSeconds,
                'last_answer_id' => $cached->lastAnswerId,
                'last_activity_at' => $cached->lastActivityAt,
                'connection_alive' => $cached->connectionAlive,
            ]);
        }

        return ApiResponse::success([
            'attempt_id' => $attempt->id,
            'time_remaining_seconds' => $attempt->getTimeRemainingSeconds(),
            'last_answer_id' => null,
            'last_activity_at' => null,
            'connection_alive' => false,
        ]);
    }
}
