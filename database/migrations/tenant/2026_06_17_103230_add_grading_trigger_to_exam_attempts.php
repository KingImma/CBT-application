<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('
            CREATE OR REPLACE FUNCTION enforce_graded_transition()
            RETURNS trigger AS $$
            BEGIN
                IF NEW.status = \'graded\' AND OLD.status IS DISTINCT FROM NEW.status AND OLD.status NOT IN (\'submitted\', \'grading\') THEN
                    RAISE EXCEPTION \'Invalid status transition to graded from %\', OLD.status USING ERRCODE = \'check_violation\';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('DROP TRIGGER IF EXISTS trigger_enforce_graded_transition ON exam_attempts;');
        DB::statement('
            CREATE TRIGGER trigger_enforce_graded_transition
            BEFORE UPDATE ON exam_attempts
            FOR EACH ROW EXECUTE FUNCTION enforce_graded_transition();
        ');

        DB::statement('
            UPDATE exam_attempts
            SET grading_started_at = COALESCE(submitted_at, updated_at)
            WHERE status = \'graded\' AND grading_started_at IS NULL;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trigger_enforce_graded_transition ON exam_attempts;');
        DB::statement('DROP FUNCTION IF EXISTS enforce_graded_transition();');
    }
};
