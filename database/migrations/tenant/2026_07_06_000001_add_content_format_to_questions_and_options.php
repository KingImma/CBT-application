<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('content_format', 20)->default('plain_text')->after('content');
        });

        Schema::table('question_options', function (Blueprint $table) {
            $table->string('content_format', 20)->default('plain_text')->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('content_format');
        });

        Schema::table('question_options', function (Blueprint $table) {
            $table->dropColumn('content_format');
        });
    }
};
