<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\Submissions;

use App\Models\Tenant\Submission;

final class RecomputeSubmissionMarks
{
    public function __construct() {}

    /**
     * Keep the submission's total_marks in step with the sum of its question
     * marks. Called after any question insert/update/delete.
     */
    public function execute(Submission $submission): Submission
    {
        $submission->update([
            'total_marks' => $submission->submissionQuestions()->sum('marks'),
        ]);

        return $submission->fresh();
    }
}
