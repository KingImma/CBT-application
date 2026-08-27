<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stamp when a schedule's results are bulk-published, mirroring the
     * single-exam `published_at`.
     */
    public function up(): void
    {
        Schema::table('assessment_schedules', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_schedules', function (Blueprint $table) {
            $table->dropColumn('published_at');
        });
    }
};
