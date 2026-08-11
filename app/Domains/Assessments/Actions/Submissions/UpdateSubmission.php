<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\Submissions;

use App\Domains\Assessments\Data\Input\UpdateSubmissionData;
use App\Domains\Assessments\Exceptions\SubmissionStateTransitionException;
use App\Models\Tenant\Submission;
use Illuminate\Support\Facades\DB;

final class UpdateSubmission
{
    public function __construct() {}

    public function execute(Submission $submission, UpdateSubmissionData $dto): Submission
    {
        return DB::transaction(function () use ($submission, $dto): Submission {
            throw_unless(
                $submission->isEditableByTeacher(),
                new SubmissionStateTransitionException(
                    'The submission can only be edited while it is a draft or has been returned for changes.'
                )
            );

            $submission->update($dto->toArray());

            return $submission->fresh();
        });
    }
}
