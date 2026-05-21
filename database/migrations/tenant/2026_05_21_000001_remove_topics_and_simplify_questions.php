<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop exam_topics (FK to exams, topics)
        Schema::dropIfExists('exam_topics');

        // Drop fill_blank_answers (FK to questions)
        Schema::dropIfExists('fill_blank_answers');

        // Drop topic_id FK and column from questions
        Schema::table('questions', function ($table) {
            $table->dropConstrainedForeignId('topic_id');
        });

        // Drop parent_id self-ref FK then drop topics
        Schema::table('topics', function ($table) {
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('topics');

        // Drop deprecated columns from questions
        Schema::table('questions', function ($table) {
            $table->dropColumn(['time_estimate_seconds', 'metadata']);
        });

        // Update type check constraint to only allow mcq_single
        DB::statement('ALTER TABLE questions DROP CONSTRAINT IF EXISTS questions_type_check');
        DB::statement("ALTER TABLE questions ADD CONSTRAINT questions_type_check CHECK (type = 'mcq_single')");
        DB::statement("ALTER TABLE questions ALTER COLUMN type SET DEFAULT 'mcq_single'");
    }

    public function down(): void
    {
        // Cannot reliably reverse - would need to recreate tables and constraints
    }
};
