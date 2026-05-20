<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // actor_type holds the model class (e.g. App\Models\SuperAdmin, App\Models\Tenant).
            // actor_id is string(100) rather than uuid because tenant IDs are slug strings
            // (e.g. "kings-college-lagos") while super admin IDs are UUIDs — both must fit here.
            $table->string('actor_type', 50);
            $table->string('actor_id', 100)->nullable();

            $table->string('action', 100);

            // Same reasoning for target_id: could reference a Tenant (slug) or any
            // other model whose primary key is a UUID.
            $table->string('target_type', 100)->nullable();
            $table->string('target_id', 100)->nullable();

            $table->jsonb('metadata')->nullable();
            $table->ipAddress('ip_address');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_audit_logs');
    }
};
