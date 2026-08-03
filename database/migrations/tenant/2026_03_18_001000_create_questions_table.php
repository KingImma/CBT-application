<?php

use App\Enums\QuestionType;
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
        Schema::create('questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignUuid('topic_id')->nullable()->constrained('topics')->nullOnDelete();
            $table->foreignUuid('class_level_id')->constrained('class_levels')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->enum('type', array_column(QuestionType::cases(), 'value'));
            $table->text('content');
            $table->text('explanation')->nullable();
            $table->integer('time_estimate_seconds')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('usage_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['subject_id', 'class_level_id']);
            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
