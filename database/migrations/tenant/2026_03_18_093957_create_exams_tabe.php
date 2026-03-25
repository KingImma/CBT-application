<?php

use App\Enums\ExamStatus;
use App\Enums\ExamType;
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
        Schema::create('exams_tabe', function (Blueprint $table) {
            $table->id()->primary();
            $table->string('title');
            $table->foreignUuid('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignUuid('class_level_id')->constrained('class_levels')->restrictOnDelete();
            $table->foreignUuid('class_arm_id')->nullable()->constrained('class_arms')->nullOnDelete();
            $table->foreignUuid('term_id')->constrained('terms')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->enum('type', array_column(ExamType::cases(), 'value'));
            $table->enum('status', array_column(ExamStatus::cases(), 'value'));
            $table->integer('duration_minutes');
            $table->decimal('total_marks', 6, 2);
            $table->decimal('pass_mark', 6, 2)->nullable();
            $table->integer('max_attempts');
            $table->timestamp('scheduled_start')->nullable();
            $table->timestamp('scheduled_end')->nullable();
            $table->jsonb('settings');
            $table->text('instructions')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['status', 'scheduled_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exams_tabe');
    }
};
