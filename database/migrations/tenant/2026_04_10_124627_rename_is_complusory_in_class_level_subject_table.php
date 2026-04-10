<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('class_level_subject', function (Blueprint $table) {
            $table->renameColumn('is_complusory', 'is_compulsory');
        });
    }

    public function down()
    {
        Schema::table('class_level_subject', function (Blueprint $table) {
            $table->renameColumn('is_compulsory', 'is_complusory');
        });
    }
};