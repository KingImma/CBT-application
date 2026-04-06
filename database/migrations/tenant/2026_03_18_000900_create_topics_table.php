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
        // 1. Create the table and all columns first
        Schema::create('topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->foreignUuid('class_level_id')->constrained('class_levels')->restrictOnDelete();
            $table->string('name');
            
            // Define the column, but DO NOT add the constraint here
            $table->uuid('parent_id')->nullable();
            
            $table->integer('order')->nullable();
            $table->timestamps();
        });

        // 2. Open a NEW block to add the self-referencing constraint
        Schema::table('topics', function (Blueprint $table) {
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('topics')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Dropping the table automatically removes its constraints
        Schema::dropIfExists('topics');
    }
};