<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignUuid('topic_id')->constrained('topics')->restrictOnDelete();
            $table->decimal('weight', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['exam_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_topics');
    }
};
