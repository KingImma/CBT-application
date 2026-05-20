<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Data\Values\ExamAttemptSettings;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class ExamSessionAction
{
    public function __construct(
        private ExamGradingAction $gradingAction,
    ) {}

    public function validateStart(Exam $exam, User $student): void
    {
        if ($exam->status !== 'active') {
            throw new \RuntimeException('Exam is not active.');
        }

        if ($exam->session_started_at === null) {
            throw new \RuntimeException('Exam session has not started.');
        }

        $sessionDeadline = $exam->session_started_at->copy()->addMinutes($exam->session_duration_minutes ?? 120);
        if (now() > $sessionDeadline) {
            throw new \RuntimeException('Exam session has ended.');
        }

        if ($exam->settings->requireAttendance) {
            $attendance = $exam->attendanceRecords()->where('student_id', $student->id)->first();
            if (! $attendance || $attendance->status !== 'present') {
                throw new \RuntimeException('Attendance not marked as present.');
            }
        }

        // Prevent concurrent in-progress attempts
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
            ->with('question.options', 'question.fillBlankAnswers', 'question.topic')
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
            $answers = ExamAnswer::where('attempt_id', $attempt->id)->get();

            foreach ($answers as $answer) {
                $this->gradingAction->autoGrade($answer);
            }

            $hasTheory = $attempt->answers()
                ->whereHas('question', fn ($q) => $q->whereIn('type', ['essay', 'short_answer']))
                ->exists();

            $timeSpentSeconds = ExamAnswer::where('attempt_id', $attempt->id)
                ->whereNotNull('time_spent_seconds')
                ->max('time_spent_seconds');

            $attempt->update([
                'status' => $hasTheory ? ExamAttemptStatus::Grading->value : ExamAttemptStatus::Graded->value,
                'submitted_at' => now(),
                'time_spent_seconds' => $timeSpentSeconds ?? now()->diffInSeconds($attempt->started_at),
            ]);

            $this->gradingAction->recomputeScore($attempt);

            return $attempt->fresh();
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
            'time_remaining_seconds' => $attempt->getTimeRemainingSeconds(),
        ];
    }
}
