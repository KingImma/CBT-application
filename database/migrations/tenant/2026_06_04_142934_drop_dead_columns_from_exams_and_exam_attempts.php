<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn(['scheduled_end']);
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->dropColumn([
                'objective_score',
                'theory_score',
                'is_theory_graded',
                'ip_address',
                'user_agent',
                'rank_in_class',
                'graded_by',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->timestamp('scheduled_end')->nullable();
        });

        Schema::table('exam_attempts', function (Blueprint $table) {
            $table->decimal('objective_score', 6, 2)->nullable();
            $table->decimal('theory_score', 6, 2)->nullable();
            $table->boolean('is_theory_graded')->default(false);
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('rank_in_class')->nullable();
            $table->foreignUuid('graded_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }
};
