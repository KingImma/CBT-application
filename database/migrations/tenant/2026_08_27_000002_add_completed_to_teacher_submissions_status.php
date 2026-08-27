<?php

use App\Enums\SubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Submission lifecycle gains a `completed` terminal status (set by the
     * ExamCompleted chain). Rebuild the enum check from the current cases
     * without touching the column, default or its composite index.
     */
    public function up(): void
    {
        $allowed = collect(SubmissionStatus::cases())
            ->map(fn ($case) => "'{$case->value}'")
            ->implode(', ');

        DB::statement('ALTER TABLE teacher_submissions DROP CONSTRAINT IF EXISTS teacher_submissions_status_check');
        DB::statement("ALTER TABLE teacher_submissions ADD CONSTRAINT teacher_submissions_status_check CHECK (status IN ({$allowed}))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE teacher_submissions DROP CONSTRAINT IF EXISTS teacher_submissions_status_check');
        DB::statement("ALTER TABLE teacher_submissions ADD CONSTRAINT teacher_submissions_status_check CHECK (status IN ('draft', 'submitted', 'changes_requested', 'approved'))");
    }
};
