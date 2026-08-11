<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Support;

use App\Domains\Assessments\Exceptions\SubmissionCannotBeReviewedException;
use App\Domains\Assessments\Exceptions\SubmissionCannotBeSubmittedException;
use App\Domains\Assessments\Exceptions\SubmissionMarksExceedCapException;
use App\Models\Tenant\Submission;
use Closure;

final class SubmissionLifecycleRules
{
    public static function canSubmitForReview(): Closure
    {
        return function (Submission $submission): void {
            throw_unless(
                $submission->isDraft() || $submission->isChangesRequested(),
                new SubmissionCannotBeSubmittedException(
                    'Only draft or returned submissions can be submitted for review.'
                )
            );

            throw_unless(
                $submission->submissionQuestions()->count() >= 1,
                new SubmissionCannotBeSubmittedException(
                    'A submission must have at least one question before it can be submitted for review.'
                )
            );

            $assessment = $submission->assessment;

            throw_unless(
                $assessment !== null && $assessment->isOpen(),
                new SubmissionCannotBeSubmittedException(
                    'The assessment is not open for submissions.'
                )
            );

            throw_unless(
                $assessment->submission_closes_at !== null
                    && $assessment->submission_closes_at->isFuture(),
                new SubmissionCannotBeSubmittedException(
                    'The submission window has closed.'
                )
            );

            throw_if(
                $submission->questionsMarksTotal() > (float) $assessment->total_marks,
                new SubmissionMarksExceedCapException(
                    "The submission's total marks ({$submission->questionsMarksTotal()}) exceed the assessment cap ({$assessment->total_marks})."
                )
            );
        };
    }

    public static function canRequestChanges(): Closure
    {
        return function (Submission $submission): void {
            throw_unless(
                $submission->isSubmitted(),
                new SubmissionCannotBeReviewedException(
                    'Only submitted submissions can have changes requested.'
                )
            );
        };
    }

    public static function canApprove(): Closure
    {
        return function (Submission $submission): void {
            throw_unless(
                $submission->isSubmitted(),
                new SubmissionCannotBeReviewedException(
                    'Only submitted submissions can be approved.'
                )
            );
        };
    }
}
