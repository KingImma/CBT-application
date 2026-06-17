<?php

declare(strict_types=1);

namespace App\Models\Tenant\Exam\Concerns;

use App\Enums\ExamStatus;
use Illuminate\Support\Carbon;

trait HasValidation
{
    public function isDraft(): bool
    {
        return $this->status === ExamStatus::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === ExamStatus::Submitted;
    }

    public function isActive(): bool
    {
        return $this->status === ExamStatus::Active;
    }

    public function isCompleted(): bool
    {
        return $this->status === ExamStatus::Completed;
    }

    public function isPublished(): bool
    {
        return $this->status === ExamStatus::Published;
    }

    public function hasExpired(): bool
    {
        return $this->window_end && $this->window_end->isPast();
    }

    public function canSubmitForReview(): bool
    {
        return $this->isDraft()
            && $this->examQuestions()->count() > 0
            && $this->total_marks > 0;
    }

    public function canActivate(): bool
    {
        return $this->isSubmitted()
            && $this->duration_minutes > 0
            && $this->pass_mark !== null
            && $this->pass_mark <= $this->total_marks
            && $this->scheduled_start !== null;
    }

    public function canComplete(): bool
    {
        return $this->isActive();
    }

    public function canRevertToDraft(): bool
    {
        return $this->isSubmitted() || $this->isActive();
    }

    public function windowEnd(): ?Carbon
    {
        return $this->window_end;
    }
}
