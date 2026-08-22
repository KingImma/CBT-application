<?php

declare(strict_types=1);

namespace App\Models\Tenant\Submission\Concerns;

use App\Enums\SubmissionStatus;

trait HasValidation
{
    public function isDraft(): bool
    {
        return $this->status === SubmissionStatus::Draft;
    }

    public function isSubmitted(): bool
    {
        return $this->status === SubmissionStatus::Submitted;
    }

    public function isChangesRequested(): bool
    {
        return $this->status === SubmissionStatus::ChangesRequested;
    }

    public function isApproved(): bool
    {
        return $this->status === SubmissionStatus::Approved;
    }

    /**
     * Teachers may edit the paper only while it is a draft or has been
     * returned for changes.
     */
    public function isEditableByTeacher(): bool
    {
        return $this->isDraft() || $this->isChangesRequested();
    }

    public function questionsMarksTotal(): float
    {
        return (float) $this->submissionQuestions()->sum('marks');
    }

    public function canSubmitForReview(): bool
    {
        if (! $this->isDraft() && ! $this->isChangesRequested()) {
            return false;
        }

        if ($this->submissionQuestions()->count() < 1) {
            return false;
        }

        $schedule = $this->schedule;

        return $schedule !== null
            && $schedule->questionWindowIsOpen()
            && $this->questionsMarksTotal() <= (float) $schedule->assessment->total_marks;
    }

    public function canRequestChanges(): bool
    {
        return $this->isSubmitted();
    }

    public function canApprove(): bool
    {
        return $this->isSubmitted();
    }
}
