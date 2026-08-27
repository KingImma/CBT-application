<?php

use App\Enums\AssessmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The `assessment_status` check was created before AssessmentStatus gained
     * its `published` case, so bulk-publishing fails on any database whose
     * migration predates that enum value (RefreshDatabase hides this by
     * re-running migrations with current enum values). Rebuild the constraint
     * from the current cases without touching the column or its indexes.
     */
    public function up(): void
    {
        $allowed = collect(AssessmentStatus::cases())
            ->map(fn ($case) => "'{$case->value}'")
            ->implode(', ');

        DB::statement('ALTER TABLE assessment_schedules DROP CONSTRAINT IF EXISTS assessment_schedules_assessment_status_check');
        DB::statement("ALTER TABLE assessment_schedules ADD CONSTRAINT assessment_schedules_assessment_status_check CHECK (assessment_status IN ({$allowed}))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE assessment_schedules DROP CONSTRAINT IF EXISTS assessment_schedules_assessment_status_check');
        DB::statement("ALTER TABLE assessment_schedules ADD CONSTRAINT assessment_schedules_assessment_status_check CHECK (assessment_status IN ('draft', 'active', 'completed'))");
    }
};
