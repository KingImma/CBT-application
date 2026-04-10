<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('class_level_subject', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_level_id')->constrained('class_levels')->restrictOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->restrictOnDelete();
            $table->boolean('is_compulsory');
            $table->timestamps();
            
            $table->unique(['class_level_id', 'subject_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('class_level_subject');
    }
};
