<?php

use App\Enums\SubmissionQuestionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Submission questions — authored inline on the submission (teachers do not
     * draw from the shared question bank here). Content lives directly on the row;
     * options are normalised into submission_question_options.
     */
    public function up(): void
    {
        Schema::create('submission_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('submissions')->cascadeOnDelete();
            $table->enum('type', array_column(SubmissionQuestionType::cases(), 'value'));
            $table->integer('order');
            $table->text('content');
            $table->text('explanation')->nullable();
            $table->decimal('marks', 6, 2)->default(0);
            $table->string('image_url', 500)->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_questions');
    }
};
