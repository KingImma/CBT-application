<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Data\Values\ExamAttemptSettings;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Events\ExamAttemptsUpdated;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\GradingScale;
use App\Models\Tenant\User;
use App\Services\Exam\GradeResolver;
use App\Services\Exam\ScoreCalculator;
use Illuminate\Support\Facades\DB;

class ExamSessionAction
{
    public function validateStart(Exam $exam, User $student): void
    {
        if ($exam->status !== ExamStatus::Active) {
            throw new \RuntimeException('Exam is not active.');
        }

        if ($exam->scheduled_start === null) {
            throw new \RuntimeException('Exam has no scheduled start time.');
        }

        if (now()->lt($exam->scheduled_start)) {
            throw new \RuntimeException('Exam has not yet started. It will be available at '.$exam->scheduled_start->toIso8601String().'.');
        }

        if ($exam->window_end !== null && now()->gte($exam->window_end)) {
            throw new \RuntimeException('The exam window has closed.');
        }

        $hasInProgress = $exam->attempts()
            ->forStudent($student->id)
            ->inProgress()
            ->exists();

        if ($hasInProgress) {
            throw new \RuntimeException('You already have an active exam attempt.');
        }

        $maxAttempts = $exam->max_attempts ?? 1;
        $lastAttempt = $exam->attempts()->forStudent($student->id)->max('attempt_number');
        if ($lastAttempt !== null && $lastAttempt >= $maxAttempts) {
            throw new \RuntimeException('Maximum attempts exceeded.');
        }

        if (! $student->is_active) {
            throw new \RuntimeException('Student account is not active.');
        }
    }

    public function startAttempt(Exam $exam, User $student): ExamAttempt
    {
        $lastAttemptNumber = $exam->attempts()->forStudent($student->id)->max('attempt_number') ?? 0;

        return DB::transaction(function () use ($exam, $student, $lastAttemptNumber) {
            return ExamAttempt::create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'attempt_number' => $lastAttemptNumber + 1,
                'status' => ExamAttemptStatus::InProgress->value,
                'started_at' => now(),
            ]);
        });
    }

    public function getQuestions(ExamAttempt $attempt): array
    {
        $exam = $attempt->exam;
        $questions = ExamQuestion::where('exam_id', $exam->id)
            ->with('question.options')
            ->orderBy('order')
            ->get();

        $questionIds = $questions->pluck('question.id')->toArray();

        if ($exam->settings->randomizeQuestions) {
            $savedOrder = $attempt->settings?->questionOrder;

            if (! empty($savedOrder)) {
                $questionIds = $savedOrder;
            } else {
                shuffle($questionIds);
                $attempt->settings = new ExamAttemptSettings(
                    questionOrder: $questionIds,
                );
                $attempt->save();
            }

            $questions = $questions->sortBy(
                fn (ExamQuestion $eq) => array_search($eq->question->id, $questionIds)
            )->values();
        }

        return [
            'questions' => $questions,
            'order' => $questionIds,
        ];
    }

    public function submit(ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
            throw new \RuntimeException('Only in-progress attempts can be submitted.');
        }

        return DB::transaction(function () use ($attempt) {
            $exam = $attempt->exam;

            $answers = ExamAnswer::with('question.options')
                ->where('attempt_id', $attempt->id)
                ->get();

            $examQuestions = ExamQuestion::where('exam_id', $exam->id)
                ->get()
                ->keyBy('question_id');

            $runningTotal = 0;
            $maxTime = 0;

            foreach ($answers as $answer) {
                $selected = $answer->selected_option_ids ?? [];
                $correctOption = $answer->question->options->firstWhere('is_correct', true);
                $isCorrect = count($selected) === 1 && $correctOption?->id === $selected[0];

                $marksAwarded = 0;
                if ($isCorrect) {
                    $eq = $examQuestions->get($answer->question_id);
                    $marksAwarded = $eq?->getEffectiveMarks() ?? $answer->question->default_marks;
                }

                $answer->updateQuietly([
                    'is_correct' => $isCorrect,
                    'marks_awarded' => $marksAwarded,
                ]);

                $runningTotal += $marksAwarded;
                $maxTime = max($maxTime, $answer->time_spent_seconds ?? 0);
            }

            $percentageScore = ScoreCalculator::percentage($runningTotal, (float) $exam->total_marks);

            $defaultScale = GradingScale::where('is_default', true)->first();
            $grade = GradeResolver::resolve($percentageScore, $defaultScale?->grades);

            $attempt->update([
                'status' => ExamAttemptStatus::Graded->value,
                'submitted_at' => now(),
                'time_spent_seconds' => $maxTime ?: (int) now()->diffInSeconds($attempt->started_at),
                'total_score' => $runningTotal,
                'percentage_score' => $percentageScore,
                'grade' => $grade,
            ]);

            $attempt->exam()->increment('completed_attempts');
            $exam->refresh();

            $shouldComplete = $exam->completed_attempts >= $exam->expected_attempts
                || ($exam->window_end !== null && now()->gte($exam->window_end));

            if ($shouldComplete) {
                $exam->update(['status' => ExamStatus::Completed]);
            }

            event(new ExamAttemptsUpdated(
                examId: $exam->id,
                completedAttempts: $exam->completed_attempts,
                expectedAttempts: $exam->expected_attempts,
                status: $shouldComplete ? ExamStatus::Completed : ExamStatus::Active,
                tenantId: (string) tenant('id'),
            ));

            return $attempt->fresh();
        });
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

        $answers = ExamAnswer::where('attempt_id', $attempt->id)
            ->get()
            ->keyBy('question_id');

        return [
            'attempt' => $attempt,
            'questions' => $questionsData['questions'],
            'order' => $questionsData['order'],
            'answers' => $answers,
            'time_remaining_seconds' => max(0, $attempt->getTimeRemainingSeconds()),
        ];
    }
}
