<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('questions', 'difficulty')) {
            return;
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('questions_subject_id_class_level_id_difficulty_index');
            $table->dropColumn('difficulty');
            $table->index(['subject_id', 'class_level_id']);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('questions', 'difficulty')) {
            return;
        }

        Schema::table('questions', function (Blueprint $table) {
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium')->after('type');
            $table->dropIndex('questions_subject_id_class_level_id_index');
            $table->index(['subject_id', 'class_level_id', 'difficulty']);
        });
    }
};
