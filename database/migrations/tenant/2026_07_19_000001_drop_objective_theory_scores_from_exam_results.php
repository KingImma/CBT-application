<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropColumn(['objective_score', 'theory_score']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->decimal('objective_score', 6, 2)->nullable();
            $table->decimal('theory_score', 6, 2)->nullable();
        });
    }
};
