<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\Exam\ExamSessionAction;
use App\Models\Tenant\ExamAttempt;
use App\Enums\ExamAttemptStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoSubmitExpiredExams extends Command
{
    protected $signature = 'exams:auto-submit-expired';
    protected $description = 'Auto-submit expired exam attempts based on individual and session timers';

    public function __construct(
        private ExamSessionAction $sessionAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Checking for expired exam attempts...');

        // Query 1: Individual attempt timer expired
        $individualExpired = ExamAttempt::where('status', ExamAttemptStatus::InProgress->value)
            ->whereHas('exam', fn ($q) => $q->whereNotNull('duration_minutes'))
            ->get()
            ->filter(fn ($attempt) => now()->getTimestamp() >= $attempt->started_at->getTimestamp() + ($attempt->exam->duration_minutes * 60));

        // Query 2: Session timer expired (ceiling for all attempts)
        $sessionExpired = ExamAttempt::where('status', ExamAttemptStatus::InProgress->value)
            ->whereHas('exam', fn ($q) => $q->whereNotNull('session_started_at')->whereNotNull('session_duration_minutes'))
            ->get()
            ->filter(fn ($attempt) => now()->getTimestamp() >= $attempt->exam->session_started_at->getTimestamp() + ($attempt->exam->session_duration_minutes * 60));

        // Merge and deduplicate
        $allExpired = $individualExpired->merge($sessionExpired)->unique('id');

        $this->info("Found {$allExpired->count()} expired attempts.");

        foreach ($allExpired as $attempt) {
            try {
                $this->sessionAction->submit($attempt);
                $this->info("Auto-submitted attempt {$attempt->id} for student {$attempt->student_id}");
            } catch (\Exception $e) {
                $this->error("Failed to submit attempt {$attempt->id}: {$e->getMessage()}");
            }
        }

        $this->info('Auto-submit process completed.');
        return self::SUCCESS;
    }
}
