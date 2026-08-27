<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Listeners;

use App\Domains\Exams\Events\ExamCompleted;
use App\Models\Tenant\Submission;

/**
 * Chains exam completion onto its teacher submission: when an exam moves to
 * `completed` (force-complete, time-expiry or the last student attempt) the
 * materialised submission is marked `completed` too, so the schedule publish
 * gate can read the submission instead of the internal exam.
 *
 * Synchronous (unlike the notification listener) so the gate sees the update
 * immediately and it joins the caller's transaction.
 */
final class MarkSubmissionCompleted
{
    public function handle(ExamCompleted $event): void
    {
        Submission::query()
            ->where('exam_id', $event->exam->id)
            ->first()
            ?->complete();
    }
}
