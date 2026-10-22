<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\ScheduleSubjects;

use App\Domains\Assessments\Data\Input\ScheduleSubjectData;
use App\Domains\Assessments\Data\Input\SubmissionScheduleSlotData;
use App\Models\Tenant\ScheduleSubject;
use App\Models\Tenant\Submission;
use Illuminate\Support\Facades\DB;

final class UpsertSubmissionScheduleSlot
{
    public function __construct(
        private AssignScheduleSubject $assign,
        private UpdateScheduleSubject $update,
    ) {
    }

    /**
     * Create or move the slot for the subject of an approved submission. The
     * subject is taken from the submission, never from the request, so a
     * teacher cannot schedule a subject they do not own.
     */
    public function execute(Submission $submission, SubmissionScheduleSlotData $dto): ScheduleSubject
    {
        return DB::transaction(function () use ($submission, $dto): ScheduleSubject {
            $schedule = $submission->schedule;

            $existing = $schedule->scheduleSubjects()
                ->where('subject_id', $submission->subject_id)
                ->first();

            $data = new ScheduleSubjectData(
                subject_id: $submission->subject_id,
                starts_at: $dto->starts_at,
                ends_at: $dto->ends_at,
                duration_minutes: $dto->duration_minutes,
            );

            return $existing !== null
                ? $this->update->execute($existing, $data)
                : $this->assign->execute($schedule, $data);
        });
    }
}