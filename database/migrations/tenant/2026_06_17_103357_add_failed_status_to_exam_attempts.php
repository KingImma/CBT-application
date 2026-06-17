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
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->enum('status', [
                'in_progress',
                'submitted',
                'graded',
                'timed_out',
                'grading',
                'disqualified',
                'failed',
            ])->default('in_progress')->after('attempt_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->enum('status', [
                'in_progress',
                'submitted',
                'graded',
                'timed_out',
                'grading',
                'disqualified',
            ])->default('in_progress')->after('attempt_number');
        });
    }
};
