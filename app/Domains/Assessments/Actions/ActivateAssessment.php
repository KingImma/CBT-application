<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Events\AssessmentActivated;
use App\Models\Tenant\AssessmentSchedule;
use Illuminate\Support\Facades\DB;

final class ActivateAssessment
{
    public function __construct(
        private MaterializeAssessmentExams $materialize,
    ) {}

    /**
     * Flip the schedule to active and materialise the student-facing paper(s)
     * from every approved submission on THIS schedule. The guard runs inside
     * activate(); materialisation shares the transaction so a failure to build
     * an exam rolls the status change back too.
     */
    public function execute(AssessmentSchedule $schedule): AssessmentSchedule
    {
        return DB::transaction(function () use ($schedule): AssessmentSchedule {
            $schedule->activate();

            $this->materialize->execute($schedule);

            $fresh = $schedule->fresh();

            event(new AssessmentActivated($fresh));

            return $fresh;
        });
    }
}
