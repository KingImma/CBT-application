<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\Submissions;

use App\Domains\Assessments\Events\SubmissionApproved;
use App\Models\Tenant\Submission;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

final class ApproveSubmission
{
    public function __construct() {}

    public function execute(Submission $submission, User $admin): Submission
    {
        return DB::transaction(function () use ($submission, $admin): Submission {
            $submission->approve($admin->id);

            $fresh = $submission->fresh();

            event(new SubmissionApproved($fresh, $admin));

            return $fresh;
        });
    }
}
