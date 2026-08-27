<?php

declare(strict_types=1);

namespace App\Models\Tenant\Submission\Concerns;

use App\Domains\Assessments\Support\SubmissionLifecycleRules;
use App\Enums\SubmissionStatus;

trait HasLifecycle
{
    public function submitForReview(): self
    {
        SubmissionLifecycleRules::canSubmitForReview()($this);

        $this->status = SubmissionStatus::Submitted;
        $this->submitted_at = now();
        $this->save();

        return $this;
    }

    /**
     * Flip to changes_requested and stamp the return time. The accompanying
     * comment + notification are dispatched by the Action inside the same
     * transaction.
     */
    public function requestChanges(): self
    {
        SubmissionLifecycleRules::canRequestChanges()($this);

        $this->status = SubmissionStatus::ChangesRequested;
        $this->returned_at = now();
        $this->save();

        return $this;
    }

    public function approve(string $approvedBy): self
    {
        SubmissionLifecycleRules::canApprove()($this);

        $this->status = SubmissionStatus::Approved;
        $this->approved_at = now();
        $this->approved_by = $approvedBy;
        $this->save();

        return $this;
    }

    /**
     * Terminal status reached when the materialised exam is completed
     * (force-complete, time-expiry or last student attempt). Idempotent —
     * the ExamCompleted chain must stay safe against an unpublish reverting
     * the exam to `completed` and re-firing.
     */
    public function complete(): self
    {
        $this->status = SubmissionStatus::Completed;
        $this->save();

        return $this;
    }
}
