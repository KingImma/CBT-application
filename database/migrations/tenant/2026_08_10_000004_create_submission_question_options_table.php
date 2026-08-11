<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Options for a submission question. Mirrors question_options: normalised
     * rows with is_correct, rather than a JSON blob — keeps grading reuse.
     */
    public function up(): void
    {
        Schema::create('submission_question_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_question_id')
                ->constrained('submission_questions')
                ->cascadeOnDelete();
            $table->string('label', 10)->nullable();
            $table->text('content');
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->integer('order');
            $table->timestamps();

            $table->index('submission_question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_question_options');
    }
};
