<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['questions', 'topics', 'exam_attempts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignUuid('academic_session_id')
                    ->nullable()
                    ->constrained('academic_sessions')
                    ->nullOnDelete();
                $table->foreignUuid('term_id')
                    ->nullable()
                    ->constrained('terms')
                    ->nullOnDelete();
            });
        }

        $session = DB::table('academic_sessions')->where('is_current', true)->first();
        if (! $session) {
            return;
        }

        $term = DB::table('terms')
            ->where('academic_session_id', $session->id)
            ->where('is_current', true)
            ->first();

        if (! $term) {
            return;
        }

        foreach (['questions', 'topics', 'exam_attempts'] as $tableName) {
            DB::table($tableName)
                ->whereNull('academic_session_id')
                ->whereNull('term_id')
                ->update([
                    'academic_session_id' => $session->id,
                    'term_id' => $term->id,
                ]);
        }
    }

    public function down(): void
    {
        foreach (['exam_attempts', 'topics', 'questions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('term_id');
                $table->dropConstrainedForeignId('academic_session_id');
            });
        }
    }
};
