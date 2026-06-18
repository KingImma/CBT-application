<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function ($table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function ($table) {
            $table->dropUnique(['name']);
        });
    }
};
