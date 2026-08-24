<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\Submissions;

use App\Domains\Assessments\Data\Input\CreateSubmissionData;
use App\Domains\Assessments\Exceptions\SubmissionCannotBeSubmittedException;
use App\Enums\SubmissionStatus;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\Submission;
use App\Models\Tenant\TeacherSubjectAssignment;
use Illuminate\Support\Facades\DB;

final class CreateSubmission
{
    public function __construct() {}

    /**
     * A teacher creates their paper inside an open schedule. The unique
     * (assessment_schedule_id, teacher_id, subject_id) index guarantees one
     * paper per teacher per subject per occurrence (decision #5). Eligibility
     * is subject-assignment against the schedule's class level.
     */
    public function execute(
        AssessmentSchedule $schedule,
        CreateSubmissionData $dto,
        string $teacherId,
    ): Submission {
        return DB::transaction(function () use ($schedule, $dto, $teacherId): Submission {
            throw_unless(
                $schedule->questionWindowIsOpen(),
                new SubmissionCannotBeSubmittedException(
                    'The question submission window has closed.'
                )
            );

            throw_unless(
                TeacherSubjectAssignment::where('user_id', $teacherId)
                    ->where('subject_id', $dto->subject_id)
                    ->where('class_level_id', $schedule->class_level_id)
                    ->exists(),
                new SubmissionCannotBeSubmittedException(
                    'You are not assigned to this subject for the scheduled class level.'
                )
            );

            // Surface the unique index as a domain conflict rather than a 500.
            throw_if(
                $schedule->submissions()
                    ->where('teacher_id', $teacherId)
                    ->where('subject_id', $dto->subject_id)
                    ->exists(),
                new SubmissionCannotBeSubmittedException(
                    'You already have a paper for this subject in this schedule.'
                )
            );

            return Submission::create([
                'assessment_schedule_id' => $schedule->id,
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
