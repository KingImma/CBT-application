<?php

declare(strict_types=1);

namespace App\Models\Tenant\Exam\Concerns;

use App\Enums\ExamStatus;
use Illuminate\Support\Facades\DB;
use App\Exceptions\Domain\Exam\{
    ExamCannotBeActivatedException, 
    ExamCannotBeCompletedException, 
    ExamCannotBeSubmittedException, 
    ExamStateTransitionException
};


trait HasLifecycle
{
    public function submitForReview(): self
    {
        throw_unless($this->canSubmitForReview(), ExamCannotBeSubmittedException::class);

        $this->status = ExamStatus::Submitted;

        return $this;
    }

    public function activate(string $userId): self
    {
        throw_unless($this->canActivate(), ExamCannotBeActivatedException::class);

        $windowEnd = $this->scheduled_start->copy()->addMinutes(
            $this->duration_minutes * 2
        );

        $expectedAttempts = $this->expectedAttempts();

        DB::transaction(function () use ($userId, $windowEnd, $expectedAttempts) {
            $this->status = ExamStatus::Active;
            $this->approved_by = $userId;
            $this->approved_at = now();
            $this->window_end = $windowEnd;
            $this->expected_attempts = $expectedAttempts;
        });

        return $this;
    }

    public function complete(): self
    {
        throw_unless($this->canComplete(), ExamCannotBeCompletedException::class);

        $this->status = ExamStatus::Completed;
        $this->window_end = now();

        return $this;
    }

    public function revertToDraft(?string $reason = null): self
    {
        throw_unless($this->canRevertToDraft(), ExamStateTransitionException::class);

        $this->status = ExamStatus::Draft;
        $this->rejection_reason = $reason;

        return $this;
    }

    public function publish(): self
    {
        throw_unless($this->status === ExamStatus::Completed, new \RuntimeException('An exam can only be published once it is completed.'));

        $this->status = ExamStatus::Published;
        $this->published_at = now();
        
        return $this;
    }

    public function unpublish(): self
    {
        throw_unless($this->status === ExamStatus::Published, new \RuntimeException('An exam can only be unpublished if it is published.'));

        $this->status = ExamStatus::Completed;
        $this->published_at = null;

        return $this;
    }
}
