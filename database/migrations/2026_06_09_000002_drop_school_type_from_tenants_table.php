<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tenants', 'school_type')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('school_type');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tenants', 'school_type')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->enum('school_type', ['Primary School', 'Secondary School', 'Mixed'])->nullable()->after('name');
            });
        }
    }
};
