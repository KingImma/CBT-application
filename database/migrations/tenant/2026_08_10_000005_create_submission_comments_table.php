<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Review thread on a submission. One level of nesting (parent_id),
     * mirrors exam_comments.
     */
    public function up(): void
    {
        Schema::create('submission_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained('teacher_submissions')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->restrictOnDelete();
            $table->uuid('parent_id')->nullable();
            $table->text('body');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('submission_id');
        });

        Schema::table('submission_comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('submission_comments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_comments');
    }
};
