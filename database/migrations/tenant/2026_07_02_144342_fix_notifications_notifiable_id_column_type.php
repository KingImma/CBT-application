<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex([
                'notifiable_type',
                'notifiable_id',
            ]);
        });

        DB::statement(
            'ALTER TABLE notifications ALTER COLUMN notifiable_id TYPE UUID USING notifiable_id::uuid'
        );

        Schema::table('notifications', function (Blueprint $table) {
            $table->index([
                'notifiable_type',
                'notifiable_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex([
                'notifiable_type',
                'notifiable_id',
            ]);
        });

        DB::statement(
            'ALTER TABLE notifications ALTER COLUMN notifiable_id TYPE BIGINT USING notifiable_id::text::bigint'
        );

        Schema::table('notifications', function (Blueprint $table) {
            $table->index([
                'notifiable_type',
                'notifiable_id',
            ]);
        });
    }
};
