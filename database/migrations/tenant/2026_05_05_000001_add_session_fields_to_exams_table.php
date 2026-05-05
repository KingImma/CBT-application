<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->timestamp('session_started_at')->nullable()->after('scheduled_end');
            $table->integer('session_duration_minutes')->default(120)->after('session_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['session_started_at', 'session_duration_minutes']);
        });
    }
};
