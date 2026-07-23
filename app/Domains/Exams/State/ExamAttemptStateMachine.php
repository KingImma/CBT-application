<?php

declare(strict_types=1);

namespace App\Domains\Exams\State;

use App\Domains\Exams\Exceptions\AttemptCannotBeSubmittedException;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use RuntimeException;

final class ExamAttemptStateMachine
{
    /** InProgress -> Submitted. */
    public function submit(ExamAttempt $attempt, ?User $actor): ExamAttemptStatus
    {
        $this->assertCanSubmit($attempt, $actor);

        return ExamAttemptStatus::Submitted;
    }

    /** Submitted -> Grading, or Grading -> Grading (idempotent re-claim for recovery). */
    public function claimForGrading(ExamAttempt $attempt): ExamAttemptStatus
    {
        $status = $this->statusOf($attempt);

        if ($status === ExamAttemptStatus::Submitted || $status === ExamAttemptStatus::Grading) {
            return ExamAttemptStatus::Grading;
        }

        throw new RuntimeException('Only submitted or grading attempts can be claimed for grading.');
    }

    /** Grading -> Graded (strict). */
    public function grade(ExamAttempt $attempt): ExamAttemptStatus
    {
        if ($this->statusOf($attempt) !== ExamAttemptStatus::Grading) {
            throw new RuntimeException('Only attempts claimed for grading can be graded.');
        }

        return ExamAttemptStatus::Graded;
    }

    /** Grading -> Failed. */
    public function fail(ExamAttempt $attempt): ExamAttemptStatus
    {
        if ($this->statusOf($attempt) !== ExamAttemptStatus::Grading) {
            throw new RuntimeException('Only grading attempts can be marked failed.');
        }

        return ExamAttemptStatus::Failed;
    }

    /** InProgress -> Timed_out. */
    public function timeout(ExamAttempt $attempt): ExamAttemptStatus
    {
        $this->assertInProgress($attempt);

        return ExamAttemptStatus::Timed_out;
    }

    /** Guard peek for the answer-save gate and the timeout precondition. */
    public function assertInProgress(ExamAttempt $attempt): void
    {
        if ($this->statusOf($attempt) !== ExamAttemptStatus::InProgress) {
            throw new RuntimeException('Attempt is no longer active.');
        }
    }

    private function assertCanSubmit(ExamAttempt $attempt, ?User $actor): void
    {
        if ($this->statusOf($attempt) !== ExamAttemptStatus::InProgress) {
            throw new RuntimeException('Only in-progress attempts can be submitted.');
        }

        if ($actor === null) {
            throw new AttemptCannotBeSubmittedException('Exam time has expired.');
        }

        if ($actor->id !== $attempt->student_id) {
            throw new RuntimeException('Unauthorized.');
        }

        if ($attempt->getTimeRemainingSeconds() <= 0) {
            throw new AttemptCannotBeSubmittedException('Exam time has expired.');
        }
    }

    private function statusOf(ExamAttempt $attempt): ?ExamAttemptStatus
    {
        $status = $attempt->status;

        return $status instanceof ExamAttemptStatus
            ? $status
            : ExamAttemptStatus::tryFrom((string) $status);
    }
}
