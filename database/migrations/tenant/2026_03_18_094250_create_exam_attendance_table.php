<?php


use App\Enums\ExamAttendanceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exam_attendance', function (Blueprint $table) {
            $table->id()->primary();
            $table->foreignUuid('exam_id')->constrained('exams')->restrictOnDelete();
            $table->foreignUuid('student_id')->constrained('users')->restrictOnDelete();
            $table->enum('status', array_column(ExamAttendanceStatus::cases(), 'value'));
            $table->foreignUuid('marked_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('marked_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['exam_id', 'student_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attendance');
    }
};
