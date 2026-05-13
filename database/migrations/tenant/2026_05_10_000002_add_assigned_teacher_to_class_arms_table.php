<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_arms', function (Blueprint $table) {
            $table->foreignUuid('assigned_teacher_id')
                ->nullable()
                ->after('class_level_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('class_arms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_teacher_id');
        });
    }
};
