<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'assessment_max_score' => '50',
            'exam_max_score' => '100',
        ] as $key => $value) {
            $exists = DB::table('school_settings')->where('key', $key)->exists();

            if ($exists) {
                continue;
            }

            DB::table('school_settings')->insert([
                    'id' => Str::uuid()->toString(),
                    'key' => $key,
                    'value' => $value,
                    'type' => 'integer',
                    'updated_at' => now(),
                    'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('school_settings')
            ->whereIn('key', ['assessment_max_score', 'exam_max_score'])
            ->delete();
    }
};
