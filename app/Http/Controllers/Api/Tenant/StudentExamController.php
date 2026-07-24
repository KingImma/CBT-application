<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Domains\Exams\Actions\Attempts\FinalizeAttempt;
use App\Domains\Exams\Actions\Attempts\GetExamQuestions;
use App\Domains\Exams\Actions\Attempts\RecordExamAnswer;
use App\Domains\Exams\Actions\Attempts\StartExamAttempt;
use App\Domains\Exams\Data\Output\ExamAttemptData;
use App\Domains\Exams\Data\Output\ResultQuestionData;
use App\Domains\Exams\Data\Output\StudentQuestionData;
use App\Domains\Exams\Support\ExamSessionStateStore;
use App\Domains\Students\Support\SebLaunchHelper;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
use App\Enums\SuspiciousEventType;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Shared\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentExamController extends Controller
{
    public function __construct(
        private StartExamAttempt $startAttempt,
        private FinalizeAttempt $finalizeAttempt,
        private RecordExamAnswer $recordAnswer,
        private GetExamQuestions $getQuestions,
        private ExamSessionStateStore $stateStore,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $student = $request->user('tenant');
        $profile = $student->studentProfile;

        $exams = Exam::where('status', ExamStatus::Active->value)
            ->where('class_level_id', $profile?->class_level_id)
            ->where(
                fn ($q) => $q
                    ->whereNull('class_arm_id')
                    ->orWhere('class_arm_id', $profile?->class_arm_id),
            )
            ->with(['subject', 'classLevel'])
            ->paginate((int) $request->get('per_page', 20));

        return ApiResponse::paginated($exams, 'Available exams retrieved.');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $exam = Exam::with(['subject', 'classLevel'])
            ->withCount('examQuestions as question_count')
            ->findOrFail($id);

        $lastAttempt = ExamAttempt::forExam($exam->id)
            ->forStudent($request->user('tenant')->id)
            ->orderByDesc('attempt_number')
            ->first();

        return ApiResponse::success(
            ['exam' => $exam, 'last_attempt' => $lastAttempt],
            'Exam details retrieved.',
        );
    }

    // ── Attempt lifecycle ─────────────────────────────────────────────────────

    public function start(Request $request, string $id, SebLaunchHelper $sebLaunchHelper): JsonResponse
    {
        $exam = Exam::findOrFail($id);
        $student = $request->user('tenant');

        try {
            $attempt = $this->startAttempt->execute($exam, $student);
        } catch (QueryException $e) {
            if (
                $e->getCode() === '23505' ||
                str_contains($e->getMessage(), 'idx_unique_in_progress_attempt')
            ) {
                return ApiResponse::error(
                    'You already have an active exam attempt.',
                    422,
                );
            }
            throw $e;
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        $questionsData = $this->getQuestions->execute($attempt);

        $sebLaunchUrl = $sebLaunchHelper->generateLaunchUrl($attempt, $student);

        return ApiResponse::created(
            [
                'attempt' => $attempt,
                'questions' => StudentQuestionData::collectFromExamQuestions(
                    $questionsData['questions'],
                ),
                'order' => $questionsData['order'],
                'seb_launch_url' => $sebLaunchUrl,
            ],
            'Exam started.',
        );
    }

    public function activeAttempt(Request $request, string $id): JsonResponse
    {
        $attempt = ExamAttempt::where('exam_id', $id)
            ->forStudent($request->user('tenant')->id)
            ->inProgress()
            ->firstOrFail();

        $questionsData = $this->getQuestions->execute($attempt);

        return ApiResponse::success(
            [
                'attempt' => $attempt,
                'questions' => StudentQuestionData::collectFromExamQuestions(
                    $questionsData['questions'],
                ),
                'order' => $questionsData['order'],
                'answers' => ExamAnswer::where('attempt_id', $attempt->id)
                    ->get()
                    ->keyBy('question_id'),
                'time_remaining_seconds' => $attempt->getTimeRemainingSeconds(),
            ],
            'Active attempt retrieved.',
        );
    }

    public function getQuestions(Request $request, string $id): JsonResponse
    {
        $attempt = ExamAttempt::where('exam_id', $id)
            ->forStudent($request->user('tenant')->id)
            ->inProgress()
            ->firstOrFail();

        $questionsData = $this->getQuestions->execute($attempt);

        return ApiResponse::success(
            [
                'exam_id' => $id,
                'attempt_id' => $attempt->id,
                'questions' => StudentQuestionData::collectFromExamQuestions(
                    $questionsData['questions'],
                ),
                'order' => $questionsData['order'],
                'time_remaining_seconds' => $attempt->getTimeRemainingSeconds(),
            ],
            'Questions retrieved.',
        );
    }

    public function getAttemptQuestions(
        Request $request,
        string $attemptId,
    ): JsonResponse {
        $attempt = ExamAttempt::with('exam')->findOrFail($attemptId);

        if ($attempt->student_id !== $request->user('tenant')->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
            return ApiResponse::error(
                'Only in-progress attempts can retrieve questions.',
                422,
            );
        }

        $questionsData = $this->getQuestions->execute($attempt);

        return ApiResponse::success(
            [
                'exam_id' => $attempt->exam_id,
                'attempt_id' => $attempt->id,
                'questions' => StudentQuestionData::collectFromExamQuestions(
                    $questionsData['questions'],
                ),
                'order' => $questionsData['order'],
                'time_remaining_seconds' => $attempt->getTimeRemainingSeconds(),
            ],
            'Questions retrieved.',
        );
    }

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

    // ── Answering ─────────────────────────────────────────────────────────────

    public function saveAnswer(
        Request $request,
        string $attemptId,
        string $examQuestionId,
    ): JsonResponse {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('saveAnswer', $attempt);

        $examQuestion = ExamQuestion::where(
            'exam_id',
            $attempt->exam_id,
        )->findOrFail($examQuestionId);

        $question = $examQuestion->question;
        $rules = $this->answerRules($question->type);
        $rules['time_spent_seconds'] = ['sometimes', 'integer', 'min:0'];

        $answer = $this->recordAnswer->save(
            $attempt,
            $question->id,
            $request->validate($rules),
        );

        return ApiResponse::success($answer, 'Answer saved.');
    }

    public function bulkSave(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::findOrFail($attemptId);
        $this->authorize('saveAnswer', $attempt);

        $validated = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'uuid'],
            'answers.*.selected_option_ids' => [
                'sometimes',
                'nullable',
                'array',
                'min:1',
            ],
            'answers.*.selected_option_ids.*' => ['uuid'],
            'answers.*.text_answer' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
            'answers.*.time_spent_seconds' => ['sometimes', 'integer', 'min:0'],
        ]);

        // Extract the incoming IDs (which are ExamQuestion IDs, not Question IDs)
        $examQuestionIds = array_column($validated['answers'], 'question_id');

        // Fetch the mapping and eager-load the actual questions
        $examQuestions = ExamQuestion::with('question')
            ->where('exam_id', $attempt->exam_id)
            ->whereIn('id', $examQuestionIds)
            ->get()
            ->keyBy('id');

        $transformedAnswers = [];

        foreach ($validated['answers'] as $ans) {
            $examQuestionId = $ans['question_id'];
            $examQuestion = $examQuestions->get($examQuestionId);

            if (! $examQuestion || ! $examQuestion->question) {
                return ApiResponse::error(
                    "Question {$examQuestionId} not found in this exam.",
                    422,
                );
            }

            $q = $examQuestion->question;
            $isFitb = $q->type === QuestionType::FillInBlank->value;
            $hasOptions = isset($ans['selected_option_ids']);
            $hasText = isset($ans['text_answer']);

            if ($isFitb && $hasOptions) {
                return ApiResponse::error(
                    "Question {$examQuestionId} is FillInBlank; selected_option_ids not accepted.",
                    422,
                );
            }
            if ($isFitb && ! $hasText) {
                return ApiResponse::error(
                    "Question {$examQuestionId} requires text_answer.",
                    422,
                );
            }
            if (! $isFitb && $hasText) {
                return ApiResponse::error(
                    "Question {$examQuestionId} is choice-based; text_answer not accepted.",
                    422,
                );
            }
            if (! $isFitb && ! $hasOptions) {
                return ApiResponse::error(
                    "Question {$examQuestionId} requires selected_option_ids.",
                    422,
                );
            }

            // Remap the ID to the underlying Question ID for the persistence layer
            $ans['question_id'] = $q->id;
            $transformedAnswers[] = $ans;
        }

        // Pass the transformed payload to the action
        $this->recordAnswer->bulkSave($attempt, $transformedAnswers);

        return ApiResponse::message('Answers saved.');
    }

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
        $isFlagged = $this->recordAnswer->toggleFlag($answer);

        return ApiResponse::success(
            ['is_flagged' => $isFlagged],
            'Flag toggled.',
        );
    }

    // ── Session / time ────────────────────────────────────────────────────────

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
            ['remaining_seconds' => $remaining, 'expired' => $remaining <= 0],
            'Time remaining retrieved.',
        );
    }

    public function sessionState(
        Request $request,
        string $attemptId,
    ): JsonResponse {
        $attempt = ExamAttempt::findOrFail($attemptId);

        if ($attempt->student_id !== $request->user('tenant')->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        $cached = $this->stateStore->read((string) tenant('id'), $attemptId);

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

    // ── Integrity ─────────────────────────────────────────────────────────────

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

    // ── Results ───────────────────────────────────────────────────────────────

    public function result(Request $request, string $attemptId): JsonResponse
    {
        $attempt = ExamAttempt::with([
            'exam.examQuestions',
            'answers.question.options',
        ])->findOrFail($attemptId);

        if ($attempt->student_id !== $request->user('tenant')->id) {
            return ApiResponse::error('Unauthorized.', 403);
        }

        if (
            ! $attempt->exam->isPublished() &&
            ! $attempt->exam->settings->getShowResultImmediately()
        ) {
            return ApiResponse::error(
                'Results for this exam have not been released yet.',
                403,
            );
        }

        $examQuestions = $attempt->exam->examQuestions->keyBy('question_id');

        $questions = $attempt->answers
            ->map(function ($answer) use ($examQuestions) {
                $eq = $examQuestions->get($answer->question->id);

                return $eq
                    ? ResultQuestionData::fromAnswer(
                        $answer,
                        $eq,
                        $answer->question,
                    )
                    : null;
            })
            ->filter()
            ->values();

        $data = ExamAttemptData::from($attempt)->toArray();
        $data['questions'] = $questions->toArray();

        return ApiResponse::success($data, 'Result retrieved.');
    }

    public function results(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exam_id' => ['sometimes', 'uuid', 'exists:exams,id'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $attempts = ExamAttempt::with([
            'exam.subject',
            'exam.examQuestions',
            'answers.question.options',
        ])
            ->where('student_id', $request->user('tenant')->id)
            ->whereIn('status', [
                ExamAttemptStatus::Graded,
                ExamAttemptStatus::Disqualified,
                ExamAttemptStatus::Timed_out,
            ])
            ->whereHas(
                'exam',
                fn ($q) => $q->where(
                    fn ($q) => $q
                        ->whereNotNull('published_at')
                        ->orWhereJsonContains(
                            'settings->show_result_immediately',
                            true,
                        ),
                ),
            )
            ->when(
                isset($validated['exam_id']),
                fn ($q) => $q->where('exam_id', $validated['exam_id']),
            )
            ->latest('submitted_at')
            ->paginate((int) ($validated['per_page'] ?? 20));

        $results = $attempts->getCollection()->map(function ($attempt) {
            $examQuestions = $attempt->exam->examQuestions->keyBy(
                'question_id',
            );

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
                'questions' => $attempt->answers
                    ->map(function ($answer) use ($examQuestions) {
                        $eq = $examQuestions->get($answer->question->id);

                        return $eq
                            ? ResultQuestionData::fromAnswer(
                                $answer,
                                $eq,
                                $answer->question,
                            )
                            : null;
                    })
                    ->filter()
                    ->values()
                    ->toArray(),
            ];
        });

        return ApiResponse::paginated(
            $attempts,
            'Results retrieved successfully.',
            $results,
        );
    }

    private function answerRules(string $questionType): array
    {
        if ($questionType === QuestionType::FillInBlank->value) {
            return [
                'selected_option_ids' => ['prohibited'],
                'text_answer' => ['required', 'string', 'max:2000'],
            ];
        }

        return [
            'selected_option_ids' => ['required', 'array', 'min:1'],
            'selected_option_ids.*' => ['uuid'],
            'text_answer' => ['prohibited'],
        ];
    }
}
