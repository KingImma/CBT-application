<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            'default_duration_minutes' => ['value' => '120', 'type' => 'integer', 'description' => 'Default exam/CA duration in minutes'],
            'default_max_attempts' => ['value' => '1', 'type' => 'integer', 'description' => 'Default maximum attempts per exam/CA'],
            'default_show_result_immediately' => ['value' => 'false', 'type' => 'boolean', 'description' => 'Default: whether results are shown immediately after submission'],
            'default_pass_mark' => ['value' => '50', 'type' => 'integer', 'description' => 'Default pass mark percentage'],
        ];

        foreach ($defaults as $key => $config) {
            $exists = DB::table('school_settings')->where('key', $key)->exists();

            if ($exists) {
                continue;
            }

            DB::table('school_settings')->insert([
                'id' => Str::uuid()->toString(),
                'key' => $key,
                'value' => $config['value'],
                'type' => $config['type'],
                'description' => $config['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('school_settings')
            ->whereIn('key', [
                'default_duration_minutes',
                'default_max_attempts',
                'default_show_result_immediately',
                'default_pass_mark',
            ])
            ->delete();
    }
};
