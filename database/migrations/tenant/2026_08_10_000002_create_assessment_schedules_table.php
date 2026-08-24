<?php

use App\Enums\AssessmentStatus;
use App\Enums\QuestionSubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AssessmentSchedule — one scheduled occurrence of an assessment, bound
     * to a class level (and optionally an arm) in a session/term:
     * "End of Term Exam → JSS1 → 1st-term schedule". Central operational
     * entity: owns both clocks (teacher question window + student exam
     * window) and both lifecycle statuses. Opening a schedule immediately
     * opens the question submission window.
     */
    public function up(): void
    {
        Schema::create('assessment_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->foreignUuid('academic_session_id')->constrained('academic_sessions')->restrictOnDelete();
            $table->foreignUuid('term_id')->constrained('terms')->restrictOnDelete();

            // The class this occurrence is for (the definition is global).
            $table->foreignUuid('class_level_id')->constrained('class_levels')->restrictOnDelete();
            $table->foreignUuid('class_arm_id')->nullable()->constrained('class_arms')->nullOnDelete();

            // Teacher question-submission deadline. The window is open from
            // creation, so only the end is stored.
            $table->timestamp('question_submission_ends');

            // Master student exam window; per-subject slots must fit inside it.
            $table->timestamp('assessment_starts')->nullable();
            $table->timestamp('assessment_ends')->nullable();

            $table->enum(
                'question_submission_status',
                array_column(QuestionSubmissionStatus::cases(), 'value')
            )->default(QuestionSubmissionStatus::Open->value);

            $table->enum(
                'assessment_status',
                array_column(AssessmentStatus::cases(), 'value')
            )->default(AssessmentStatus::Draft->value);

            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'class_level_id', 'term_id']);
            $table->index(['class_level_id', 'assessment_status']);
            $table->index(['question_submission_status', 'question_submission_ends']);
            $table->index(['assessment_status', 'assessment_starts']);
            $table->index(['assessment_status', 'assessment_ends']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_schedules');
    }
};
