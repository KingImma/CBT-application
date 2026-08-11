<?php

use App\Enums\SubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Submission — one teacher's paper inside an assessment. This is the
     * former "Exam" demoted to live under an assessment container.
     */
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', array_column(SubmissionStatus::cases(), 'value'))
                ->default(SubmissionStatus::Draft->value);
            $table->decimal('total_marks', 6, 2)->default(0);

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();

            // Set when activation materialises the student-facing exam.
            $table->foreignUuid('exam_id')->nullable()->constrained('exams')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // One paper per teacher per subject within an assessment (decision #5).
            $table->unique(['assessment_id', 'teacher_id', 'subject_id']);
            $table->index(['assessment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
