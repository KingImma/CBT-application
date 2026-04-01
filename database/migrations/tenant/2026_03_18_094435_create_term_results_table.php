<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('term_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignUuid('term_id')->constrained('terms')->restrictOnDelete();
            $table->foreignUuid('class_level_id')->constrained('class_levels')->restrictOnDelete();
            $table->decimal('ca_score', 5, 2)->nullable();
            $table->decimal('exam_score', 5, 2)->nullable();
            $table->decimal('total_score', 5, 2)->nullable();
            $table->string('grade', 5)->nullable();
            $table->integer('rank_in_class')->nullable();
            $table->integer('rank_in_level')->nullable();
            $table->text('teacher_remark')->nullable();
            $table->timestamps();
            
            $table->unique(['student_id', 'subject_id', 'term_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('term_results');
    }
};
