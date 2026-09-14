<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->index(['term_id', 'subject_id', 'student_id'], 'idx_exam_results_broadsheet');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->index(['class_level_id', 'class_arm_id'], 'idx_student_profiles_class_scope');
        });
    }

    public function down(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropIndex('idx_exam_results_broadsheet');
        });

        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropIndex('idx_student_profiles_class_scope');
        });
    }
};
