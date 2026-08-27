<?php

use App\Domains\Assessments\Support\BackfillSubmissionCompletion;
use Illuminate\Database\Migrations\Migration;

/**
 * Corrective backfill promoting submissions to `completed` for exams that
 * finished before the ExamCompleted chain shipped. Idempotent; shared with
 * the `assessments:mark-submissions-completed` command.
 */
return new class extends Migration
{
    public function up(): void
    {
        $backfill = new BackfillSubmissionCompletion;

        if ($backfill->pendingCount() === 0) {
            return;
        }

        $backfill->upgrade();
    }

    /**
     * Data backfills are not reversible; use migrate:fresh to reset instead.
     */
    public function down(): void {}
};
