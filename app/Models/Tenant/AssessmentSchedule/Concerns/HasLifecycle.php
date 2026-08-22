<?php

declare(strict_types=1);

namespace App\Models\Tenant\AssessmentSchedule\Concerns;

use App\Domains\Assessments\Support\AssessmentLifecycleRules;
use App\Enums\AssessmentStatus;
use App\Enums\QuestionSubmissionStatus;

trait HasLifecycle
{
    /**
     * Close the teacher question-submission window. Triggered manually or by
     * the tick once question_submission_ends has passed.
     */
    public function closeSubmissions(): self
    {
        AssessmentLifecycleRules::canCloseSubmissions()($this);

        $this->question_submission_status = QuestionSubmissionStatus::Closed;
        $this->save();

        return $this;
    }

    /** Reopen a closed question window with a new future deadline. */
    public function reopenSubmissions(): self
    {
        AssessmentLifecycleRules::canReopen()($this);

        $this->question_submission_status = QuestionSubmissionStatus::Open;
        $this->save();

        return $this;
    }

    /** Flip to active and stamp the activation time; materialisation happens in the action. */
    public function activate(): self
    {
        AssessmentLifecycleRules::canActivate()($this);

        $this->assessment_status = AssessmentStatus::Active;
        $this->activated_at = now();
        $this->save();

        return $this;
    }

    public function complete(): self
    {
        AssessmentLifecycleRules::canComplete()($this);

        $this->assessment_status = AssessmentStatus::Completed;
        $this->save();

        return $this;
    }
}
