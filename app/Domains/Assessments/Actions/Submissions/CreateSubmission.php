<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\Submissions;

use App\Domains\Assessments\Data\Input\CreateSubmissionData;
use App\Domains\Assessments\Exceptions\SubmissionCannotBeSubmittedException;
use App\Enums\SubmissionStatus;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\Submission;
use App\Models\Tenant\TeacherSubjectAssignment;
use Illuminate\Support\Facades\DB;

final class CreateSubmission
{
    public function __construct() {}

    /**
     * A teacher creates their paper inside an open assessment. The unique
     * (assessment_id, teacher_id, subject_id) index guarantees one paper per
     * teacher per subject (decision #5). Eligibility is subject-assignment
     * against the assessment's class level (decision #1).
     */
    public function execute(
        Assessment $assessment,
        CreateSubmissionData $dto,
        string $teacherId,
    ): Submission {
        return DB::transaction(function () use ($assessment, $dto, $teacherId): Submission {
            throw_unless(
                $assessment->submissionWindowIsOpen(),
                new SubmissionCannotBeSubmittedException(
                    'The assessment is not open for submissions.'
                )
            );

            throw_unless(
                TeacherSubjectAssignment::where('user_id', $teacherId)
                    ->where('subject_id', $dto->subject_id)
                    ->where('class_level_id', $assessment->class_level_id)
                    ->exists(),
                new SubmissionCannotBeSubmittedException(
                    'You are not assigned to this subject for the assessment class level.'
                )
            );

            // Surface the unique index as a domain conflict rather than a 500.
            throw_if(
                $assessment->submissions()
                    ->where('teacher_id', $teacherId)
                    ->where('subject_id', $dto->subject_id)
                    ->exists(),
                new SubmissionCannotBeSubmittedException(
                    'You already have a paper for this subject in this assessment.'
                )
            );

            return Submission::create([
                'assessment_id' => $assessment->id,
                'teacher_id' => $teacherId,
                'subject_id' => $dto->subject_id,
                'title' => $dto->title,
                'description' => $dto->description,
                'status' => SubmissionStatus::Draft->value,
                'total_marks' => 0,
            ]);
        });
    }
}
