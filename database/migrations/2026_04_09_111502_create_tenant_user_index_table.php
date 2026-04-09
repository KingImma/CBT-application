<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user_index', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('tenant_id'); // FK to tenants.id (slug)
            $table->string('role', 50);
            $table->timestamps();

            $table->unique(['email', 'tenant_id']);
            $table->foreign('tenant_id')
                  ->references('id')
                  ->on('tenants')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_index');
    }
};