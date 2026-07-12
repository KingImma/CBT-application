<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('type'); // 'teacher' | 'student'
            $table->string('status')->default('pending'); // pending | processing | completed | failed
            $table->binary('file_contents');
            $table->json('meta');
            $table->timestamp('retain_until')->nullable(); // set only when status → 'failed'
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('retain_until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_jobs');
    }
};
