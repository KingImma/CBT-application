<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assessment definition — school-wide and stable across academic sessions.
     * Bound to no class: class levels (and arms) attach to the schedules that
     * occur under this definition. All occurrence data (windows, lifecycle
     * status) also lives on assessment_schedules.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->decimal('total_marks', 6, 2)->default(0);
            $table->integer('duration_minutes')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
