<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Support;

use App\Enums\ExamStatus;
use App\Enums\SubmissionStatus;
use App\Models\Tenant\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Corrective backfill for the ExamCompleted chain, which only fires going
 * forward. Exams that were already completed (or published) before the chain
 * shipped leave their materialised submission on `approved`, blocking schedule
 * publishing. This promotes those submissions to `completed` idempotently.
 *
 * Shared by the corrective migration and the
 * `assessments:mark-submissions-completed` command.
 */
final class BackfillSubmissionCompletion
{
    public function pendingCount(): int
    {
        return $this->query()->count();
    }

    public function upgrade(): int
    {
        return DB::transaction(function (): int {
            $completed = 0;

            foreach ($this->query()->get() as $submission) {
                $submission->complete();
                $completed++;
            }

            return $completed;
        });
    }

    private function query(): Builder
    {
        return Submission::query()
            ->whereNotNull('exam_id')
            ->where('status', SubmissionStatus::Approved->value)
            ->whereHas('exam', fn ($q) => $q
                ->whereIn('status', [
                    ExamStatus::Completed->value,
                    ExamStatus::Published->value,
                ])
            );
    }
}
