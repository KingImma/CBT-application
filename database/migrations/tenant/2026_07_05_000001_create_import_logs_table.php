<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // teacher|student
            $table->string('filename');
            $table->string('status'); // pending|processing|completed|failed
            $table->unsignedInteger('total_rows')->nullable();
            $table->unsignedInteger('imported')->nullable();
            $table->unsignedInteger('skipped')->nullable();
            $table->unsignedInteger('updated')->nullable();
            $table->json('errors')->nullable();
            $table->json('meta')->nullable();
            $table->foreignUuid('created_by')->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
