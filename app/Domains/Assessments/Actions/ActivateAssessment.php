<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Events\AssessmentActivated;
use App\Models\Tenant\Assessment;
use Illuminate\Support\Facades\DB;

final class ActivateAssessment
{
    public function __construct(
        private MaterializeAssessmentExams $materialize,
    ) {}

    /**
     * Flip the assessment to active and materialise the student-facing paper(s)
     * from every approved submission. The guard (≥1 approved, valid window) runs
     * inside activate(); materialisation happens in the same transaction so a
     * failure to build the exam rolls the status change back too.
     */
    public function execute(Assessment $assessment): Assessment
    {
        return DB::transaction(function () use ($assessment): Assessment {
            $assessment->activate();

            $this->materialize->execute($assessment);

            $fresh = $assessment->fresh();

            event(new AssessmentActivated($fresh));

            return $fresh;
        });
    }
}
