<?php

use App\Enums\ExamStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('exams')
            ->where('status', ExamStatus::Active->value)
            ->where(function ($q) {
                $q->where('window_end', '<', now())
                    ->orWhereColumn('completed_attempts', '>=', 'expected_attempts');
            })
            ->update(['status' => ExamStatus::Completed->value]);
    }

    public function down(): void
    {
        // No sensible rollback for a data migration.
    }
};
