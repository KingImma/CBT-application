<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Models\Tenant\Assessment;
use Illuminate\Support\Facades\DB;

final class DeleteAssessment
{
    public function __construct() {}

    /**
     * Delete the definition (cascades its schedules). Blocked while any
     * occurrence is active or completed — real exams and attempts exist.
     */
    public function execute(Assessment $assessment): void
    {
        DB::transaction(function () use ($assessment): void {
            $locked = $assessment->schedules()
                ->whereIn('assessment_status', ['active', 'completed'])
                ->exists();

            throw_if(
                $locked,
                new AssessmentStateTransitionException(
                    'The assessment cannot be deleted while one of its schedules is active or completed.'
                )
            );

            $assessment->delete();
        });
    }
}
