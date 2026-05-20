<?php

use App\Enums\ExamAttemptStatus;
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
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignUuid('student_id')->constrained('users')->restrictOnDelete();
            $table->integer('attempt_number');
            $table->enum('status', array_column(ExamAttemptStatus::cases(), 'value'));
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->integer('time_spent_seconds')->nullable();
            $table->decimal('total_score', 6, 2)->nullable();
            $table->decimal('percentage_score', 5, 2)->nullable();
            $table->string('grade', 5)->nullable();
            $table->integer('rank_in_class')->nullable();
            $table->decimal('objective_score', 6, 2)->nullable();
            $table->decimal('theory_score', 6, 2)->nullable();
            $table->boolean('is_theory_graded')->default(false);
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('suspicious_events')->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'student_id', 'attempt_number']);

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
