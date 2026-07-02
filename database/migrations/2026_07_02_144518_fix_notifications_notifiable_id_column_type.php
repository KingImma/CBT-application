<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->uuid('notifiable_id')->change();
        });
    }

    public function down(): void
    {
        // Cannot reliably reverse UUID back to bigint without data loss.
    }
};
