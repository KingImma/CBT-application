<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_type_check');

        DB::table('questions')
            ->whereIn('type', ['mcq_single', 'mcq_multi'])
            ->update(['type' => 'mcq']);

        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_type_check CHECK (type IN ('mcq', 'true_false', 'fill_in_blank'))");
        DB::statement("ALTER TABLE questions ALTER COLUMN type SET DEFAULT 'mcq'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_type_check');

        DB::table('questions')
            ->where('type', 'mcq')
            ->update(['type' => 'mcq_single']);

        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_type_check CHECK (type IN ('mcq_single', 'true_false', 'fill_in_blank'))");
        DB::statement("ALTER TABLE questions ALTER COLUMN type SET DEFAULT 'mcq_single'");
    }
};
