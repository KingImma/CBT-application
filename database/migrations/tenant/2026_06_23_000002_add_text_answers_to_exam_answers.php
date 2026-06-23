<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('exam_answers', 'selected_option_ids')) {
            DB::statement('ALTER TABLE exam_answers ALTER COLUMN selected_option_ids DROP NOT NULL');
        }

        if (! Schema::hasColumn('exam_answers', 'text_answer')) {
            Schema::table('exam_answers', function (Blueprint $table) {
                $table->text('text_answer')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('exam_answers', 'selected_option_ids')) {
            DB::statement('ALTER TABLE exam_answers ALTER COLUMN selected_option_ids DROP NOT NULL');
        }
    }
};
