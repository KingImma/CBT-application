<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->integer('expected_attempts')->default(0)->after('max_attempts');
            $table->integer('completed_attempts')->default(0)->after('expected_attempts');
            $table->dateTime('window_end')->nullable()->after('scheduled_end');
        });

        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'session_started_at')) {
                $table->dropColumn('session_started_at');
            }
        });

        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'session_duration_minutes')) {
                $table->dropColumn('session_duration_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['expected_attempts', 'completed_attempts', 'window_end']);
            $table->dateTime('session_started_at')->nullable();
            $table->integer('session_duration_minutes')->nullable();
        });
    }
};
