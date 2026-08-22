<?php

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
        Schema::create('subjects_schedule', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('assesment_id')->constrained('assesments')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->integer('duration_minutes')->nullable(); //falls to assesment default time if null;
            $table->timestamps();

            $table->unique(['assesment_id', 'subject_id']);
            $table->index(['assesment_id', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects_schedule');
    }
};
