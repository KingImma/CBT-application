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
        DB::table('school_settings')
            ->where('key', 'max_subjects_per_day')
            ->update(['description' => 'Maximum number of subjects a class arm may sit on one day.']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('school_settings')
            ->where('key', 'max_subjects_per_day')
            ->update(['description' => 'Maximum number of subjects a class level may sit on one day.']);
    }
};
