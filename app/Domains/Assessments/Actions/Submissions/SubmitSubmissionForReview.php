<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\Submissions;

use App\Domains\Assessments\Events\SubmissionSubmitted;
use App\Models\Tenant\Submission;
use Illuminate\Support\Facades\DB;

final class SubmitSubmissionForReview
{
    public function __construct() {}

    public function execute(Submission $submission): Submission
    {
        return DB::transaction(function () use ($submission): Submission {
            $submission->submitForReview();

            $fresh = $submission->fresh();

            event(new SubmissionSubmitted($fresh));

            return $fresh;
        });
    }
}
