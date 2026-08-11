<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\Submissions;

use App\Domains\Assessments\Events\SubmissionChangesRequested;
use App\Models\Tenant\Submission;
use App\Models\Tenant\SubmissionComment;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

final class RequestSubmissionChanges
{
    public function __construct() {}

    /**
     * Return a submission to its teacher. The review comment, the status flip,
     * the return timestamp and the teacher notification all commit in one
     * transaction — a guard rejection on requestChanges() rolls the comment back
     * and the event never fires, so no notification is sent without the flip.
     */
    public function execute(Submission $submission, User $admin, string $comment): Submission
    {
        return DB::transaction(function () use ($submission, $admin, $comment): Submission {
            $submissionComment = SubmissionComment::create([
                'submission_id' => $submission->id,
                'author_id' => $admin->id,
                'body' => $comment,
            ]);

            $submission->requestChanges();

            $fresh = $submission->fresh();

            event(new SubmissionChangesRequested($fresh, $admin, $submissionComment));

            return $fresh;
        });
    }
}
