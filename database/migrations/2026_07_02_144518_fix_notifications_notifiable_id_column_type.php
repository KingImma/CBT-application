<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE notifications ALTER COLUMN notifiable_id TYPE UUID USING notifiable_id::uuid');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications ALTER COLUMN notifiable_id TYPE BIGINT USING notifiable_id::text::bigint');
    }
};
