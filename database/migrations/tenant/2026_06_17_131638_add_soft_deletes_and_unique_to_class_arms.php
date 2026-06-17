<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_arms', function (Blueprint $table) {
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX uq_class_arms_name ON class_arms (class_level_id, name) WHERE deleted_at IS NULL;');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_class_arms_name;');

        Schema::table('class_arms', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
