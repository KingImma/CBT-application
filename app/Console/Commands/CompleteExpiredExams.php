<?php

namespace App\Console\Commands;

use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompleteExpiredExams extends Command
{
    protected $signature = 'exams:complete-expired';

    protected $description = 'Mark active exams as completed when their window has expired.';

    public function handle(): void
    {
        $count = Exam::query()
            ->where('status', ExamStatus::Active)
            ->where('window_end', '<', now())
            ->update(['status' => ExamStatus::Completed]);

        if ($count > 0) {
            Log::info("Completed {$count} expired exam(s).");
        }
    }
}
