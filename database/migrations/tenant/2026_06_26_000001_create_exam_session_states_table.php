<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_session_states', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->uuid('attempt_id');
            $table->integer('time_remaining_seconds')->default(0);
            $table->boolean('connection_alive')->default(false);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'attempt_id']);
            $table->index('attempt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_session_states');
    }
};
