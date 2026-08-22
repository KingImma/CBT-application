<?php

declare(strict_types=1);

namespace App\Models\Tenant\AssessmentSchedule\Concerns;

use App\Enums\AssessmentStatus;
use App\Enums\QuestionSubmissionStatus;
use App\Enums\SubmissionStatus;
use Illuminate\Support\Carbon;

trait HasValidation
{
    public function isDraft(): bool
    {
        return $this->assessment_status === AssessmentStatus::Draft;
    }

    public function isActive(): bool
    {
        return $this->assessment_status === AssessmentStatus::Active;
    }

    public function isCompleted(): bool
    {
        return $this->assessment_status === AssessmentStatus::Completed;
    }

    public function isQuestionSubmissionOpen(): bool
    {
        return $this->question_submission_status === QuestionSubmissionStatus::Open;
    }

    public function isQuestionSubmissionClosed(): bool
    {
        return $this->question_submission_status === QuestionSubmissionStatus::Closed;
    }

    /** The teacher window is open while the schedule is live and the deadline has not passed. */
    public function questionWindowIsOpen(): bool
    {
        return $this->isQuestionSubmissionOpen()
            && $this->question_submission_ends !== null
            && $this->question_submission_ends->isFuture();
    }

    public function approvedSubmissionsCount(): int
    {
        return $this->submissions()->where('status', SubmissionStatus::Approved)->count();
    }

    public function masterWindowIsSet(): bool
    {
        return $this->assessment_starts !== null
            && $this->assessment_ends !== null
            && $this->assessment_starts < $this->assessment_ends;
    }

    public function canCloseSubmissions(): bool
    {
        return $this->isQuestionSubmissionOpen();
    }

    public function canReopen(): bool
    {
        return $this->isQuestionSubmissionClosed() && $this->isDraft();
    }

    public function canActivate(): bool
    {
        return $this->isQuestionSubmissionClosed()
            && $this->masterWindowIsSet()
            && $this->approvedSubmissionsCount() > 0;
    }

    public function canComplete(): bool
    {
        return $this->isActive();
    }

    public function studentWindowEnd(): ?Carbon
    {
        return $this->assessment_ends;
    }
}
