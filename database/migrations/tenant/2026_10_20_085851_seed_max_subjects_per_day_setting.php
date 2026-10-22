<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('school_settings')->where('key', 'max_subjects_per_day')->exists()) {
            return;
        }

        DB::table('school_settings')->insert([
            'id' => Str::uuid()->toString(),
            'key' => 'max_subjects_per_day',
            'value' => '3',
            'type' => 'integer',
            'description' => 'Maximum number of subjects a class level may sit on one day.',
            'updated_at' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('school_settings')->where('key', 'max_subjects_per_day')->delete();
    }
};
