<?php

declare(strict_types=1);

namespace App\Models\Tenant\Assessment\Concerns;

use App\Domains\Assessments\Support\AssessmentLifecycleRules;
use App\Enums\AssessmentStatus;

trait HasLifecycle
{
    public function open(): self
    {
        AssessmentLifecycleRules::canOpen()($this);

        $this->status = AssessmentStatus::Open;
        $this->submission_opens_at ??= now();
        $this->save();

        return $this;
    }

    public function closeSubmissions(): self
    {
        AssessmentLifecycleRules::canCloseSubmissions()($this);

        $this->status = AssessmentStatus::SubmissionsClosed;
        $this->save();

        return $this;
    }

    public function reopen(): self
    {
        AssessmentLifecycleRules::canReopen()($this);

        $this->status = AssessmentStatus::Open;
        $this->save();

        return $this;
    }

    public function activate(): self
    {
        AssessmentLifecycleRules::canActivate()($this);

        $this->status = AssessmentStatus::Active;
        $this->activated_at = now();
        $this->save();

        return $this;
    }

    public function complete(): self
    {
        AssessmentLifecycleRules::canComplete()($this);

        $this->status = AssessmentStatus::Completed;
        $this->save();

        return $this;
    }
}
