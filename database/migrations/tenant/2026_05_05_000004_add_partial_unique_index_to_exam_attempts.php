<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX idx_unique_in_progress_attempt 
            ON exam_attempts (exam_id, student_id) 
            WHERE status = 'in_progress'
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_unique_in_progress_attempt');
    }
};
