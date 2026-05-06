<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            // Using an enum is usually best for gender to maintain data consistency
            // Added after admission_number or whichever column makes logical sense
            $table->enum('gender', ['male', 'female', 'other'])->nullable()->after('admission_number');
            
            // Alternatively, if you prefer a simpler string column:
            // $table->string('gender', 10)->nullable()->after('admission_number');
        });
    }

    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn('gender');
        });
    }
};