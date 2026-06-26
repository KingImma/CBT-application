<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Actions\Exam\CalculateScore;
use App\Actions\Exam\ResolveGrade;
use App\Data\Values\ExamAttemptSettings;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Events\ExamAttemptsUpdated;
use App\Events\ExamSessionStateUpdated;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\GradingScale;
use App\Models\Tenant\User;
use App\Support\Exam\ExamAttemptGuard;
use App\Support\Exam\ExamSessionState;
use App\Support\Exam\ExamSessionStateStore;
use App\Support\QuestionGrader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManageExamSession
{
    public function __construct(
        private ExamSessionStateStore $stateStore,
        private QuestionGrader $questionGrader,
    ) {}

    public function validateStart(Exam $exam, User $student): void
    {
        if ($exam->status !== ExamStatus::Active) {
            throw new \RuntimeException("Exam is not active.");
        }

        if ($exam->scheduled_start === null) {
            throw new \RuntimeException("Exam has no scheduled start time.");
        }

        if (now()->lt($exam->scheduled_start)) {
            throw new \RuntimeException(
                "Exam has not yet started. It will be available at " .
                    $exam->scheduled_start->toIso8601String() .
                    ".",
            );
        }

        $examWindowHasClosed =
            $exam->window_end !== null && now()->gte($exam->window_end);

        if ($examWindowHasClosed) {
            throw new \RuntimeException("The exam window has closed.");
        }

        $hasInProgress = $exam
            ->attempts()
            ->forStudent($student->id)
            ->inProgress()
            ->exists();

        if ($hasInProgress) {
            throw new \RuntimeException(
                "You already have an active exam attempt.",
            );
        }

        $maxAttempts = $exam->max_attempts ?? 1;
        $lastAttempt = $exam
            ->attempts()
            ->forStudent($student->id)
            ->max("attempt_number");
        $maxAttemptsExceeded =
            $lastAttempt !== null && $lastAttempt >= $maxAttempts;

        if ($maxAttemptsExceeded) {
            throw new \RuntimeException("Maximum attempts exceeded.");
        }

        if (!$student->is_active) {
            throw new \RuntimeException("Student account is not active.");
        }
    }

    public function startAttempt(Exam $exam, User $student): ExamAttempt
    {
        $lastAttemptNumber =
            $exam
                ->attempts()
                ->forStudent($student->id)
                ->max("attempt_number") ?? 0;

        $attempt = DB::transaction(function () use (
            $exam,
            $student,
            $lastAttemptNumber,
        ) {
            return ExamAttempt::create([
                "exam_id" => $exam->id,
                "student_id" => $student->id,
                "attempt_number" => $lastAttemptNumber + 1,
                "status" => ExamAttemptStatus::InProgress->value,
                "started_at" => now(),
            ]);
        });

        $this->initializeSessionState($attempt);

        return $attempt;
    }

    private function initializeSessionState(ExamAttempt $attempt): void
    {
        $tenantId = (string) tenant("id");
        $remaining = $attempt->getTimeRemainingSeconds();
        $ttl = $attempt->exam->duration_minutes * 60 + 60;

        $this->stateStore->write(
            new ExamSessionState(
                attemptId: $attempt->id,
                tenantId: $tenantId,
                timeRemainingSeconds: $remaining,
                connectionAlive: true,
            ),
            $ttl,
        );

        event(
            new ExamSessionStateUpdated(
                attemptId: $attempt->id,
                tenantId: $tenantId,
                timeRemainingSeconds: $remaining,
                connectionAlive: true,
            ),
        );
    }

    public function getQuestions(ExamAttempt $attempt): array
    {
        $exam = $attempt->exam;
        $questions = ExamQuestion::where("exam_id", $exam->id)
            ->with("question.options")
            ->orderBy("order")
            ->get();

        $questionIds = $questions->pluck("question.id")->toArray();

        if (!$exam->settings->getRandomizeQuestions()) {
            return [
                "questions" => $questions,
                "order" => $questionIds,
            ];
        }

        $savedOrder = $attempt->settings?->getQuestionOrder();

        if (!empty($savedOrder)) {
            $questionIds = $savedOrder;
        } else {
            shuffle($questionIds);
            $attempt->settings = new ExamAttemptSettings(
                questionOrder: $questionIds,
            );
            $attempt->save();
        }

        $questions = $questions
            ->sortBy(
                fn(ExamQuestion $eq) => array_search(
                    $eq->question->id,
                    $questionIds,
                ),
            )
            ->values();

        return [
            "questions" => $questions,
            "order" => $questionIds,
        ];
    }

    public function submit(ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
            throw new \RuntimeException(
                "Only in-progress attempts can be submitted.",
            );
        }

        return DB::transaction(function () use ($attempt) {
            $attempt->update([
                "status" => ExamAttemptStatus::Submitted->value,
                "submitted_at" => now(),
            ]);

            return $this->gradeAttempt($attempt->fresh(), $attempt->exam);
        });
    }

    public function gradeAttempt(ExamAttempt $attempt, Exam $exam): ExamAttempt
    {
        ExamAttemptGuard::assertCanTransitionTo(
            $attempt,
            ExamAttemptStatus::Grading,
        );

        $attempt->status = ExamAttemptStatus::Grading->value;
        $attempt->save();

        $answers = ExamAnswer::with("question.options")
            ->where("attempt_id", $attempt->id)
            ->get();

        $examQuestions = ExamQuestion::where("exam_id", $exam->id)
            ->get()
            ->keyBy("question_id");

        $runningTotal = 0;
        $runningTime = 0;

        foreach ($answers as $answer) {
            $result = $this->awardMarksForAnswer($answer, $examQuestions);
            $runningTotal += $result["marks"];
            $runningTime += $result["time"];
        }

        $percentageScore = CalculateScore::execute(
            $runningTotal,
            (float) $exam->total_marks,
        );

        $defaultScale = GradingScale::where("is_default", true)->first();
        $grade = ResolveGrade::execute(
            $percentageScore,
            $defaultScale?->grades,
        );

        $attempt->status = ExamAttemptStatus::Graded->value;
        $attempt->time_spent_seconds =
            $runningTime ?: (int) now()->diffInSeconds($attempt->started_at);
        $attempt->total_score = $runningTotal;
        $attempt->percentage_score = $percentageScore;
        $attempt->grade = $grade;
        $attempt->save();

        $attempt->exam()->increment("completed_attempts");
        $exam->refresh();

        $shouldComplete =
            $exam->completed_attempts >= $exam->expected_attempts ||
            ($exam->window_end !== null && now()->gte($exam->window_end));

        if ($shouldComplete) {
            $exam->update(["status" => ExamStatus::Completed]);
        }

        event(
            new ExamAttemptsUpdated(
                examId: $exam->id,
                completedAttempts: $exam->completed_attempts,
                expectedAttempts: $exam->expected_attempts,
                status: $shouldComplete
                    ? ExamStatus::Completed
                    : ExamStatus::Active,
                tenantId: (string) tenant("id"),
            ),
        );

        return $attempt->fresh();
    }

    private function awardMarksForAnswer(
        ExamAnswer $answer,
        Collection $examQuestions,
    ): array {
        $isCorrect = $this->questionGrader->isCorrect(
            questionType: $answer->question->type,
            options: $answer->question->options,
            selectedIds: $answer->selected_option_ids ?? [],
            textAnswer: $answer->text_answer,
        );

        $marksAwarded = 0;
        if ($isCorrect) {
            $eq = $examQuestions->get($answer->question_id);
            $marksAwarded =
                $eq?->getEffectiveMarks() ?? $answer->question->default_marks;
        }

        $answer->updateQuietly([
            "is_correct" => $isCorrect,
            "marks_awarded" => $marksAwarded,
        ]);

        return [
            "marks" => $marksAwarded,
            "time" => $answer->time_spent_seconds ?? 0,
        ];
    }

    public function finalizeExpiredAttempt(ExamAttempt $attempt): ExamAttempt
    {
        return DB::transaction(function () use ($attempt) {
            $attempt->refresh();

            if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
                return $attempt->fresh();
            }

            if ($attempt->getTimeRemainingSeconds() > 0) {
                return $attempt->fresh();
            }

            return $this->submit($attempt);
        });
    }

    public function recover(ExamAttempt $attempt): array
    {
        $questionsData = $this->getQuestions($attempt);

        $answers = ExamAnswer::where("attempt_id", $attempt->id)
            ->get()
            ->keyBy("question_id");

        return [
            "attempt" => $attempt,
            "questions" => $questionsData["questions"],
            "order" => $questionsData["order"],
            "answers" => $answers,
            "time_remaining_seconds" => max(
                0,
                $attempt->getTimeRemainingSeconds(),
            ),
        ];
    }
}
