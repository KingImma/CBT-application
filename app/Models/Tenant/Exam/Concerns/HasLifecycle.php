<?php

declare(strict_types=1);

namespace App\Models\Tenant\Exam\Concerns;

use App\Enums\ExamStatus;
use App\Exceptions\Domain\Exam\ExamCannotBeActivatedException;
use App\Exceptions\Domain\Exam\ExamCannotBeCompletedException;
use App\Exceptions\Domain\Exam\ExamCannotBeSubmittedException;
use App\Exceptions\Domain\Exam\ExamStateTransitionException;

trait HasLifecycle
{
    public function submitForReview(): self
    {
        throw_unless(
            $this->canSubmitForReview(),
            ExamCannotBeSubmittedException::class
        );

        $this->status = ExamStatus::Submitted;
        $this->save();

        return $this;
    }

    public function activate(string $userId): self
    {
        throw_unless(
            $this->canActivate(),
            ExamCannotBeActivatedException::class
        );

        $windowEnd = $this->scheduled_start->copy()->addMinutes(
            $this->duration_minutes * 2
        );

        $expectedAttempts = $this->expectedAttempts();

        $this->status = ExamStatus::Active;
        $this->approved_by = $userId;
        $this->approved_at = now();
        $this->window_end = $windowEnd;
        $this->expected_attempts = $expectedAttempts;
        $this->save();

        return $this;
    }

    public function complete(): self
    {
        throw_unless(
            $this->canComplete(),
            ExamCannotBeCompletedException::class
        );

        $this->status = ExamStatus::Completed;
        $this->window_end = now();
        $this->save();

        return $this;
    }

    public function revertToDraft(?string $reason = null): self
    {
        throw_unless(
            $this->canRevertToDraft(),
            ExamStateTransitionException::class
        );

        $this->status = ExamStatus::Draft;
        $this->rejection_reason = $reason;
        $this->save();

        return $this;
    }

    public function publish(): self
    {
        throw_unless(
            $this->isCompleted(),
            ExamStateTransitionException::class,
            'An exam can only be published once it is completed.'
        );
        $this->status = ExamStatus::Published;
        $this->published_at = now();
        $this->save();

        return $this;
    }

    public function unpublish(): self
    {
        throw_unless(
            $this->isPublished(),
            ExamStateTransitionException::class,
            'An exam can only be unpublished if it is published.'
        );

        $this->status = ExamStatus::Completed;
        $this->published_at = null;
        $this->save();

        return $this;
    }
}
