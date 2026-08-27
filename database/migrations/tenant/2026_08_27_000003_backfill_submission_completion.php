<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Superseded by 2026_08_27_000004_normalize_status_constraints_and_backfill.
 *
 * On drifted production tenants the status CHECK constraint still carries its
 * legacy name (from before the `submissions` -> `teacher_submissions` rename),
 * so the earlier constraint-swap migrations left a parallel constraint that
 * rejects `completed` — running the backfill here re-raises SQLSTATE[23514].
 * This migration runs BEFORE the normalizing one in the same pass, so it is a
 * no-op; 000004 rebuilds the constraints first, then runs the same idempotent
 * backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        //
    }

    /**
     * Data backfills are not reversible; use migrate:fresh to reset instead.
     */
    public function down(): void {}
};
