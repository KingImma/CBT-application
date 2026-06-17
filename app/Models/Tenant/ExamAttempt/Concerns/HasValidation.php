<?php

declare(strict_types=1);

namespace App\Models\Tenant\ExamAttempt\Concerns;

use App\Enums\ExamAttemptStatus;
use DateTimeInterface;

trait HasValidation
{
    public function isInProgress(): bool
    {
        return $this->status === ExamAttemptStatus::InProgress;
    }

    public function isSubmitted(): bool
    {
        return $this->status === ExamAttemptStatus::Submitted;
    }

    public function isGraded(): bool
    {
        return $this->status === ExamAttemptStatus::Graded;
    }

    public function isExpired(?DateTimeInterface $now = null): bool
    {
        $currentTimestamp = ($now ?? now())->getTimestamp();

        return $currentTimestamp >= $this->getDeadlineTimestamp();
    }

    public function getTimeRemainingSeconds(?DateTimeInterface $now = null): int
    {
        $currentTimestamp = ($now ?? now())->getTimestamp();
        $remaining = $this->getDeadlineTimestamp() - $currentTimestamp;

        return max(0, $remaining);
    }

    private function getDeadlineTimestamp(): int
    {
        return $this->started_at->getTimestamp()
            + ($this->exam->duration_minutes * 60);
    }

    public function canSubmit(): bool
    {
        return $this->isInProgress() && ! $this->isExpired();
    }
}
