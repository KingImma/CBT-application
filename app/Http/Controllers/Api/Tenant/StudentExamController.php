<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Actions\Tenants\Exam\FinalizeAttempt;
use App\Actions\Tenants\Exam\ManageExamSession;
use App\Actions\Tenants\Exam\RecordExamAnswer;
use App\Data\Exam\Output\ExamAttemptData;
use App\Data\Exam\Output\ResultQuestionData;
use App\Data\Exam\Output\StudentQuestionData;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Enums\SuspiciousEventType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\Question;
use App\Support\ApiResponse;
use App\Support\DatabaseHelper;
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
     * Return validation rules for a given question type.
     *
     * - Choice-based (MCQ, TrueFalse): require selected_option_ids, reject text_answer.
     * - Text-based (FillInBlank): require text_answer, reject selected_option_ids.
     *
     * @return array<string, mixed>
     */
    private function answerRulesByType(string $questionType): array
    {
        if ($questionType === QuestionType::FillInBlank->value) {
            return [
                "selected_option_ids" => ["prohibited"],
                "text_answer" => ["required", "string", "max:2000"],
            ];
        }

        return [
            "selected_option_ids" => ["required", "array", "min:1"],
            "selected_option_ids.*" => ["uuid"],
            "text_answer" => ["prohibited"],
        ];
    }

    /**
     * List available exams for the authenticated student.
     *
     * @subgroup Available Exams
     *
     * @queryParam per_page int Results per page (default: 20). No-example
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get("per_page", 20);

        $exams = Exam::where("status", ExamStatus::Active->value)
            ->where(function ($q) use ($request) {
                $q->where(
                    "class_level_id",
                    $request->user("tenant")->studentProfile?->class_level_id,
                )->where(function ($q2) use ($request) {
                    $q2->whereNull("class_arm_id")->orWhere(
                        "class_arm_id",
                        $request->user("tenant")->studentProfile?->class_arm_id,
                    );
                });
            })
            ->with(["subject", "classLevel"])
            ->paginate($perPage);

        return ApiResponse::paginated($exams, "Available exams retrieved.");
    }

    /**
     * List the authenticated student's published results.
     *
     * Each question in the results is type-specific:
     * - mcq / true_false: includes options with is_correct revealed, and selected_options.
     * - fill_in_blank: includes text_answer and acceptable_answers; no selected_option_ids.
     *
     * @subgroup Results
     *
     * @queryParam exam_id string Filter by exam UUID. No-example
     * @queryParam per_page int Results per page (default: 20). No-example
     *
     * @responseField data.*.questions.*.type string The question type.
     * @responseField data.*.questions.*.options array|null Present for mcq/true_false with is_correct revealed.
     * @responseField data.*.questions.*.selected_options array|null Student's selected options (mcq/true_false).
     * @responseField data.*.questions.*.text_answer string|null Student's text answer (fill_in_blank).
     * @responseField data.*.questions.*.acceptable_answers array|null Acceptable answers (fill_in_blank).
     */
    public function results(Request $request): JsonResponse
    {
        $validated = $request->validate([
            "exam_id" => ["sometimes", "uuid", "exists:exams,id"],
            "per_page" => ["sometimes", "integer", "min:1", "max:100"],
        ]);

        $perPage = (int) ($validated["per_page"] ?? 20);
        $student = $request->user("tenant");

        $attempts = ExamAttempt::with([
            "exam.subject",
            "exam.classLevel",
            "exam.examQuestions",
            "answers.question.options",
        ])
            ->where("student_id", $student->id)
            ->whereIn("status", [
                ExamAttemptStatus::Graded->value,
                ExamAttemptStatus::Disqualified->value,
                ExamAttemptStatus::Timed_out->value,
            ])
            ->whereHas("exam", function ($query) {
                $query->whereNotNull("published_at");
            })
            ->when(
                isset($validated["exam_id"]),
                fn($query) => $query->where("exam_id", $validated["exam_id"]),
            )
            ->latest("submitted_at")
            ->paginate($perPage);

        $results = $attempts->getCollection()->map(function ($attempt) {
            $examQuestions = $attempt->exam->examQuestions->keyBy(
                "question_id",
            );

            $questions = $attempt->answers
                ->map(function ($answer) use ($examQuestions) {
                    $question = $answer->question;
                    $examQuestion = $examQuestions->get($question->id);

                    if ($examQuestion === null) {
                        return null;
                    }

                    return ResultQuestionData::fromAnswer(
                        $answer,
                        $examQuestion,
                        $question,
                    );
                })
                ->filter()
                ->values()
                ->toArray();

            return [
                "attempt_id" => $attempt->id,
                "exam_id" => $attempt->exam_id,
                "exam_title" => $attempt->exam->title,
                "exam_subject" => $attempt->exam->subject?->name,
                "status" => $attempt->status,
                "attempt_number" => $attempt->attempt_number,
                "total_score" => (float) $attempt->total_score,
                "total_marks" => (float) $attempt->exam->total_marks,
                "percentage_score" => (float) $attempt->percentage_score,
                "grade" => $attempt->grade,
                "submitted_at" => $attempt->submitted_at?->toIso8601String(),
                "time_spent_seconds" => $attempt->time_spent_seconds,
                "questions" => $questions,
            ];
        });

        return ApiResponse::paginated(
            $attempts,
            "Results retrieved successfully.",
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
        $exam = Exam::with(["subject", "classLevel"])
            ->withCount("examQuestions as question_count")
            ->findOrFail($id);

        $lastAttempt = ExamAttempt::forExam($exam->id)
            ->forStudent($request->user("tenant")->id)
            ->orderByDesc("attempt_number")
            ->first();

        return ApiResponse::success(
            [
                "exam" => $exam,
                "last_attempt" => $lastAttempt,
            ],
            "Exam details retrieved.",
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
        $student = $request->user("tenant");

        try {
            $this->sessionAction->validateStart($exam, $student);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        try {
            $attempt = $this->sessionAction->startAttempt($exam, $student);
        } catch (QueryException $e) {
            $isDuplicateAttempt =
                DatabaseHelper::isUniqueViolation($e) ||
                str_contains(
                    $e->getMessage(),
                    "idx_unique_in_progress_attempt",
                );

            if (!$isDuplicateAttempt) {
                throw $e;
            }

            return ApiResponse::error(
                "You already have an active exam attempt.",
                422,
            );
        }

        $questionsData = $this->sessionAction->getQuestions($attempt);

        return ApiResponse::created(
            [
                "attempt" => $attempt,
                "questions" => StudentQuestionData::collectFromExamQuestions(
                    $questionsData["questions"],
                ),
                "order" => $questionsData["order"],
            ],
            "Exam started.",
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
        $student = $request->user("tenant");

        $attempt = ExamAttempt::where("exam_id", $exam->id)
            ->forStudent($student->id)
            ->inProgress()
            ->first();

        if (!$attempt) {
            return ApiResponse::error("No active attempt found.", 404);
        }

        $data = $this->sessionAction->recover($attempt);
        $data["questions"] = StudentQuestionData::collectFromExamQuestions(
            $data["questions"],
        );

        return ApiResponse::success($data, "Active attempt retrieved.");
    }

    /**
     * Get questions for an active exam attempt.
     *
     * Returns type-specific question objects with correct answers stripped:
     * - mcq / true_false: options contain only id and content (no is_correct).
     * - fill_in_blank: no options or acceptable answers returned.
     *
     * @subgroup Answering
     *
     * @urlParam id string required The exam UUID.
     *
     * @responseField questions array Type-specific question objects.
     * @responseField questions.*.type string The question type.
     * @responseField questions.*.options array|null Present for mcq/true_false with {id, content} only.
     * @responseField questions.*.order array Question ID order.
     */
    public function getQuestions(Request $request, string $id): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $student = $request->user("tenant");

        $attempt = ExamAttempt::where("exam_id", $exam->id)
            ->forStudent($student->id)
            ->inProgress()
            ->first();

        if (!$attempt) {
            return ApiResponse::error(
                "No active attempt found for this exam.",
                404,
            );
        }

        $questionsData = $this->sessionAction->getQuestions($attempt);

        return ApiResponse::success(
            [
                "exam_id" => $exam->id,
                "attempt_id" => $attempt->id,
                "questions" => StudentQuestionData::collectFromExamQuestions(
                    $questionsData["questions"],
                ),
                "order" => $questionsData["order"],
                "time_remaining_seconds" => $attempt->getTimeRemainingSeconds(),
            ],
            "Questions retrieved.",
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
        $attempt = ExamAttempt::with("exam")->findOrFail($id);
        $student = $request->user("tenant");

        if ($attempt->student_id !== $student->id) {
            return ApiResponse::error("Unauthorized.", 403);
        }

        if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
            return ApiResponse::error(
                "Only in-progress attempts can retrieve questions.",
                422,
            );
        }

        $questionsData = $this->sessionAction->getQuestions($attempt);

        return ApiResponse::success(
            [
                "exam_id" => $attempt->exam_id,
                "attempt_id" => $attempt->id,
                "questions" => StudentQuestionData::collectFromExamQuestions(
                    $questionsData["questions"],
                ),
                "order" => $questionsData["order"],
                "time_remaining_seconds" => $attempt->getTimeRemainingSeconds(),
            ],
            "Questions retrieved.",
        );
    }

    /**
    * Save an answer for a question in an attempt.
    *
    * $questionId in the URL = ExamQuestion.id (the `id` field from StudentQuestionData).
    * We resolve through ExamQuestion → Question to get the correct question_id FK for storage.
    */
    public function saveAnswer(
        Request $request,
        string $attemptId,
        string $questionId,
    ): JsonResponse {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('saveAnswer', $attempt);

        // $questionId = ExamQuestion.id (what StudentQuestionData exposes as `id`)
        // Must resolve to actual Question to get correct FK for exam_answers.question_id.
        // Scope to exam_id for security — prevents cross-exam answer injection.
        $examQuestion = ExamQuestion::where('exam_id', $attempt->exam_id)
            ->where('id', $questionId)
            ->with('question.options')
            ->firstOrFail();

        $question = $examQuestion->question;

        $rules = $this->answerRulesByType($question->type);
        $rules['time_spent_seconds'] = ['sometimes', 'integer', 'min:0'];

        $validated = $request->validate($rules);

        // Pass $question->id (Question PK), NOT $questionId (ExamQuestion PK).
        // This is the FK that exam_answers.question_id must store for grading to work.
        $answer = $this->answerAction->save($attempt, $question->id, $validated);

        return ApiResponse::success($answer, 'Answer saved.');
    }

    /**
    * Bulk save answers for an attempt.
    *
    * answers.*.question_id in the body may be ExamQuestion.id OR Question.id depending
    * on client implementation. Resolve both cases through ExamQuestion to normalize to
    * actual Question.id before storage.
    */
    public function bulkSave(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('saveAnswer', $attempt);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'uuid'],
            'answers.*.selected_option_ids' => ['sometimes', 'nullable', 'array'],
            'answers.*.selected_option_ids.*' => ['uuid'],
            'answers.*.text_answer' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'answers.*.time_spent_seconds' => ['sometimes', 'integer', 'min:0'],
        ]);

        $submittedIds = array_unique(array_column($validated['answers'], 'question_id'));

        // Dual-mode resolution: client may send ExamQuestion.id OR Question.id.
        // Build ExamQuestion map keyed by ExamQuestion.id AND by question_id.
        $examQuestions = ExamQuestion::where('exam_id', $attempt->exam_id)
            ->whereIn('id', $submittedIds)            // ExamQuestion.id submissions
            ->orWhere(function ($q) use ($attempt, $submittedIds) {
                $q->where('exam_id', $attempt->exam_id)
                    ->whereIn('question_id', $submittedIds); // Question.id submissions
            })
            ->with('question.options')
            ->get();

        // Keyed by ExamQuestion.id for URL-style lookups
        $byExamQuestionId = $examQuestions->keyBy('id');
        // Keyed by question_id for direct Question.id lookups
        $byQuestionId = $examQuestions->keyBy('question_id');

        // Resolve and normalise each answer to actual Question.id before type-checking
        $resolvedAnswers = [];

        foreach ($validated['answers'] as $answer) {
            $submittedId = $answer['question_id'];

            if ($byExamQuestionId->has($submittedId)) {
                $question = $byExamQuestionId->get($submittedId)->question;
            } elseif ($byQuestionId->has($submittedId)) {
                $question = $byQuestionId->get($submittedId)->question;
            } else {
                return ApiResponse::error(
                    "Question {$submittedId} not found in this exam.",
                    422,
                );
            }

            if ($question === null) {
                return ApiResponse::error("Question {$submittedId} could not be resolved.", 422);
            }

            $hasOptions = isset($answer['selected_option_ids']);
            $hasText    = isset($answer['text_answer']);

            if ($question->type === QuestionType::FillInBlank->value) {
                if ($hasOptions) {
                    return ApiResponse::error(
                        "Question {$submittedId} is FillInBlank; selected_option_ids not accepted.",
                        422,
                    );
                }
                if (! $hasText) {
                    return ApiResponse::error(
                        "Question {$submittedId} requires text_answer.",
                        422,
                    );
                }
            } else {
                if ($hasText) {
                    return ApiResponse::error(
                        "Question {$submittedId} is choice-based; text_answer not accepted.",
                        422,
                    );
                }
                if (! $hasOptions) {
                    return ApiResponse::error(
                        "Question {$submittedId} requires selected_option_ids.",
                        422,
                    );
                }
            }

            // Normalise question_id to actual Question.id before forwarding to the action
            $resolvedAnswers[] = array_merge($answer, ['question_id' => $question->id]);
        }

        $this->answerAction->bulkSave($attempt, $resolvedAnswers);

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

        if ($attempt->student_id !== $request->user("tenant")->id) {
            return ApiResponse::error("Unauthorized.", 403);
        }

        $remaining = $attempt->getTimeRemainingSeconds();

        return ApiResponse::success(
            [
                "remaining_seconds" => $remaining,
                "expired" => $remaining <= 0,
            ],
            "Time remaining retrieved.",
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
        $this->authorize("submit", $attempt);

        if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
            return ApiResponse::error("Already submitted.", 409);
        }

        try {
            $attempt = $this->finalizeAttempt->execute(
                $attempt,
                $request->user("tenant"),
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success(
            ["attempt_id" => $attempt->id],
            "Exam submitted for grading.",
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
        $this->authorize("saveAnswer", $attempt);

        $answer = ExamAnswer::where("attempt_id", $attemptId)
            ->where("question_id", $questionId)
            ->firstOrFail();

        $isFlagged = $this->answerAction->toggleFlag($answer);

        return ApiResponse::success(
            ["is_flagged" => $isFlagged],
            "Flag toggled.",
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
        $this->authorize("saveAnswer", $attempt);

        $validated = $request->validate([
            "type" => [
                "required",
                "string",
                Rule::in(array_column(SuspiciousEventType::cases(), "value")),
            ],
            "metadata" => ["sometimes", "array"],
        ]);

        $attempt->logSuspiciousEvent(
            SuspiciousEventType::from($validated["type"]),
            $validated["metadata"] ?? [],
        );
        $attempt->save();

        return ApiResponse::message("Suspicious event logged.");
    }

    /**
     * Get the result for a completed exam attempt, including per-question
     * type-specific result data.
     *
     * @subgroup Results
     *
     * @urlParam attemptId string required The attempt UUID.
     *
     * @responseField data.id string The attempt UUID.
     * @responseField data.status string Attempt status.
     * @responseField data.total_score float Total score earned.
     * @responseField data.questions array Type-specific question results.
     */
    public function result(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::with([
            "exam.examQuestions",
            "answers.question.options",
        ])->findOrFail($attemptId);
        $student = $request->user("tenant");

        if ($attempt->student_id !== $student->id) {
            return ApiResponse::error("Unauthorized.", 403);
        }

        $exam = $attempt->exam;

        if (!$exam->isPublished()) {
            return ApiResponse::error(
                "Results for this exam have not been released yet.",
                403,
            );
        }

        $examQuestions = $exam->examQuestions->keyBy("question_id");

        $questions = $attempt->answers
            ->map(function ($answer) use ($examQuestions) {
                $question = $answer->question;
                $examQuestion = $examQuestions->get($question->id);

                if ($examQuestion === null) {
                    return null;
                }

                return ResultQuestionData::fromAnswer(
                    $answer,
                    $examQuestion,
                    $question,
                );
            })
            ->filter()
            ->values();

        // Build a response envelope that includes the attempt + per-question results
        $data = ExamAttemptData::from($attempt)->toArray();
        $data["questions"] = $questions->toArray();

        return ApiResponse::success($data, "Result retrieved.");
    }

    /**
     * Get the current session state for reconnection.
     *
     * @subgroup Exam Attempts
     *
     * @urlParam attemptId string required The attempt UUID.
     */
    public function sessionState(
        Request $request,
        string $attemptId,
    ): JsonResponse {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $student = $request->user("tenant");

        if ($attempt->student_id !== $student->id) {
            return ApiResponse::error("Unauthorized.", 403);
        }

        $tenantId = (string) tenant("id");
        $cached = $this->stateStore->read($tenantId, $attemptId);

        if ($cached !== null) {
            return ApiResponse::success([
                "attempt_id" => $cached->attemptId,
                "time_remaining_seconds" => $cached->timeRemainingSeconds,
                "last_answer_id" => $cached->lastAnswerId,
                "last_activity_at" => $cached->lastActivityAt,
                "connection_alive" => $cached->connectionAlive,
            ]);
        }

        return ApiResponse::success([
            "attempt_id" => $attempt->id,
            "time_remaining_seconds" => $attempt->getTimeRemainingSeconds(),
            "last_answer_id" => null,
            "last_activity_at" => null,
            "connection_alive" => false,
        ]);
    }
}
