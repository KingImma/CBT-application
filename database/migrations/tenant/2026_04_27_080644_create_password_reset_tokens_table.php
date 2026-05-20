<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('password_reset_tokens')) {
            Schema::table('password_reset_tokens', function (Blueprint $table) {
                if (Schema::hasColumn('password_reset_tokens', 'id')) {
                    $table->id();
                }
                if (! Schema::hasColumn('password_reset_tokens', 'token')) {
                    $table->string('token')->after('email');
                }
                if (! Schema::hasColumn('password_reset_tokens', 'attempts')) {
                    $table->unsignedTinyInteger('attempts')->default(0)->after('token');
                }
                if (! Schema::hasColumn('password_reset_tokens', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('attempts');
                }
                if (! Schema::hasColumn('password_reset_tokens', 'created_at')) {
                    $table->timestamp('created_at')->useCurrent()->after('expires_at');
                }
            });
        } else {
            // Create table if it doesn't exist
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->id();
                $table->string('email')->index();
                $table->string('token');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
