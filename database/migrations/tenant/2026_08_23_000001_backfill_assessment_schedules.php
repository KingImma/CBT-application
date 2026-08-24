<?php

use App\Domains\Assessments\Support\BackfillAssessmentSchedules;
use Illuminate\Database\Migrations\Migration;

/**
 * Corrective backfill upgrading every pre-"global assessment" database to
 * the occurrence model (generations A/B/fresh — see
 * {@see BackfillAssessmentSchedules} for the full logic, which is shared
 * with `tenants:ensure-assessment-schedule-format`).
 *
 * NOTE: tenants that already recorded an EARLIER version of this same file
 * will never re-run it via tenants:migrate. The command exists to heal
 * those; it runs this exact logic idempotently.
 */
return new class extends Migration
{
    public function up(): void
    {
        $backfill = new BackfillAssessmentSchedules;

        if (! $backfill->isLegacyFormat()) {
            return; // fresh database — global definitions already in place
        }

        $backfill->upgrade();
    }

    /**
     * Data backfills are not reversible; use migrate:fresh to reset instead.
     */
    public function down(): void {}
};
