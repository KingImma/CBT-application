<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('exam_answers', 'selected_optons_ids')) {
            DB::statement("ALTER TABLE exam_answers RENAME COLUMN selected_optons_ids TO selected_option_ids");
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_answers', 'selected_option_ids')) {
            DB::statement("ALTER TABLE exam_answers RENAME COLUMN selected_option_ids TO selected_optons_ids");
        }
    }
};
