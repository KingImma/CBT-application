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
        Schema::create('class_arm_subject', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('class_arm_id');
            $table->uuid('subject_id');
            $table->boolean('is_compulsory')->default(false);
            $table->timestamps();

            $table->unique(['class_arm_id', 'subject_id']);

            $table->foreign('class_arm_id')
                ->references('id')->on('class_arms')
                ->cascadeOnDelete();

            $table->foreign('subject_id')
                ->references('id')->on('subjects')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_arm_subject');
    }
};
