<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Actions;

use App\Domains\Assessments\Exceptions\AssessmentStateTransitionException;
use App\Models\Tenant\Assessment;
use Illuminate\Support\Facades\DB;

final class DeleteAssessment
{
    public function __construct() {}

    public function execute(Assessment $assessment): void
    {
        DB::transaction(function () use ($assessment): void {
            throw_unless(
                $assessment->isDraft() || $assessment->isOpen(),
                new AssessmentStateTransitionException(
                    'Only draft or open assessments can be deleted.'
                )
            );

            $assessment->delete();
        });
    }
}
