<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions\ScheduleSubjects;

use App\Domains\Assessments\Exceptions\AssessmentStateTransitionException;
use App\Models\Tenant\ScheduleSubject;
use Illuminate\Support\Facades\DB;

final class RemoveScheduleSubject
{
    public function execute(ScheduleSubject $scheduleSubject): void
    {
        DB::transaction(function () use ($scheduleSubject): void {
            $assessment = $scheduleSubject->assessment;

            // once materialized (active/completed), the exam already exists off this
            // window — deleting the slot would orphan the Exam's schedule reference
            throw_if(
                $assessment->isActive() || $assessment->isCompleted(),
                new AssessmentStateTransitionException(
                    'Cannot remove a subject slot after the assessment has been activated.'
                )
            );

            $scheduleSubject->delete();
        });
    }
}