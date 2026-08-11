<?php

declare(strict_types=1);

namespace App\Models\Tenant\Assessment\Concerns;

use App\Enums\AssessmentStatus;
use Illuminate\Support\Carbon;

trait HasValidation
{
    public function isDraft(): bool
    {
        return $this->status === AssessmentStatus::Draft;
    }

    public function isOpen(): bool
    {
        return $this->status === AssessmentStatus::Open;
    }

    public function isSubmissionsClosed(): bool
    {
        return $this->status === AssessmentStatus::SubmissionsClosed;
    }

    public function isActive(): bool
    {
        return $this->status === AssessmentStatus::Active;
    }

    public function isCompleted(): bool
    {
        return $this->status === AssessmentStatus::Completed;
    }

    public function approvedSubmissionsCount(): int
    {
        return $this->submissions()->where('status', \App\Enums\SubmissionStatus::Approved)->count();
    }

    public function submissionWindowIsOpen(): bool
    {
        return $this->isOpen()
            && $this->submission_closes_at !== null
            && $this->submission_closes_at->isFuture();
    }

    public function canOpen(): bool
    {
        return $this->isDraft()
            && $this->submission_closes_at !== null
            && $this->submission_closes_at->isFuture();
    }

    public function canCloseSubmissions(): bool
    {
        return $this->isOpen();
    }

    public function canReopen(): bool
    {
        return $this->isSubmissionsClosed();
    }

    public function canActivate(): bool
    {
        return $this->isSubmissionsClosed()
            && $this->student_starts_at !== null
            && $this->student_ends_at !== null
            && $this->student_starts_at < $this->student_ends_at
            && $this->approvedSubmissionsCount() > 0;
    }

    public function canComplete(): bool
    {
        return $this->isActive();
    }

    public function studentWindowEnd(): ?Carbon
    {
        return $this->student_ends_at;
    }
}
