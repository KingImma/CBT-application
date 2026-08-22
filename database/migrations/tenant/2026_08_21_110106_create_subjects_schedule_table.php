<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ScheduleSubject — one subject's exam slot inside an AssessmentSchedule.
     * Slots are bounded by the schedule's master student window
     * (assessment_starts / assessment_ends) and may not overlap each other.
     */
    public function up(): void
    {
        Schema::create('schedule_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_schedule_id')->constrained('assessment_schedules')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->integer('duration_minutes')->nullable(); // falls back to assessment default when null
            $table->timestamps();

            $table->unique(['assessment_schedule_id', 'subject_id']);
            $table->index(['assessment_schedule_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_subjects');
    }
};
