<?php

use App\Enums\AssessmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assessment container — admin-owned, holds many teacher submissions,
     * carries the two timer windows (teacher submission window + student window).
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->foreignUuid('class_level_id')->constrained('class_levels')->restrictOnDelete();
            $table->foreignUuid('class_arm_id')->nullable()->constrained('class_arms')->nullOnDelete();
            $table->foreignUuid('term_id')->constrained('terms')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', array_column(AssessmentStatus::cases(), 'value'))
                ->default(AssessmentStatus::Draft->value);
            $table->decimal('total_marks', 6, 2)->default(0);
            $table->integer('duration_minutes')->nullable();

            // Teacher submission window.
            $table->timestamp('submission_opens_at')->nullable();
            $table->timestamp('submission_closes_at')->nullable();

            // Student attempt window.
            $table->timestamp('student_starts_at')->nullable();
            $table->timestamp('student_ends_at')->nullable();

            $table->timestamp('activated_at')->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'submission_closes_at']);
            $table->index(['status', 'student_starts_at']);
            $table->index(['status', 'student_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
