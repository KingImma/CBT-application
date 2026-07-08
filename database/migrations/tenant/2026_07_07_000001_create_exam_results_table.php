<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exam_attempt_id')->constrained('exam_attempts')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignUuid('term_id')->constrained('terms')->restrictOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->decimal('total_score', 6, 2)->nullable();
            $table->decimal('percentage_score', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->decimal('objective_score', 6, 2)->nullable();
            $table->decimal('theory_score', 6, 2)->nullable();
            $table->boolean('is_theory_graded')->default(false);
            $table->integer('rank_in_class')->nullable();
            $table->boolean('passed')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique('exam_attempt_id');
            $table->index(['student_id', 'exam_id']);
            $table->index(['academic_session_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_results');
    }
};
