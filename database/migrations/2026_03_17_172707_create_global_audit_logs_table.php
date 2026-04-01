<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('global_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('actor_type', 50);
            $table->uuid('actor_id')->nullable();
            $table->string('action', 100);
            $table->string('target_type', 100)->nullable();
            $table->uuid('target_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->ipAddress('ip_address');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_audit_logs');
    }
};
