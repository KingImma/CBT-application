<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\Submissions;

use App\Domains\Assessments\Exceptions\SubmissionStateTransitionException;
use App\Models\Tenant\Submission;
use App\Models\Tenant\SubmissionQuestion;
use Illuminate\Support\Facades\DB;

final class RemoveSubmissionQuestion
{
    public function __construct(
        private RecomputeSubmissionMarks $recompute,
    ) {}

    public function execute(Submission $submission, SubmissionQuestion $question): void
    {
        DB::transaction(function () use ($submission, $question): void {
            throw_unless(
                $submission->isEditableByTeacher(),
                new SubmissionStateTransitionException(
                    'Questions can only be changed while the submission is a draft or returned for changes.'
                )
            );

            throw_unless(
                $question->submission_id === $submission->id,
                new SubmissionStateTransitionException(
                    'The question does not belong to this submission.'
                )
            );

            $question->options()->delete();
            $question->delete();

            $this->recompute->execute($submission);
        });
    }
}
