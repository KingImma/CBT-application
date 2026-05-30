<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->foreignUuid('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->after('session_duration_minutes');
            $table->timestamp('approved_at')
                ->nullable()
                ->after('approved_by');
            $table->text('rejection_reason')
                ->nullable()
                ->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'rejection_reason']);
        });
    }
};
