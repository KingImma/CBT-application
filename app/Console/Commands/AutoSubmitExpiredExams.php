<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\Exam\ExamSessionAction;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

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
        $tenants = Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant) {
                $this->info("Checking tenant: {$tenant->id}");

                $individualExpired = $this->expiredByTimer();
                $sessionExpired = $this->expiredBySession();
                $allExpired = $individualExpired->merge($sessionExpired)->unique('id');

                foreach ($allExpired as $attempt) {
                    try {
                        $this->sessionAction->submit($attempt);
                        $this->info("  Auto-submitted attempt {$attempt->id} for student {$attempt->student_id}");
                    } catch (\Exception $e) {
                        $this->error("  Failed to submit attempt {$attempt->id}: {$e->getMessage()}");
                    }
                }
            });
        }

        return self::SUCCESS;
    }

    private function expiredByTimer(): Collection
    {
        return ExamAttempt::with('exam')
            ->where('status', ExamAttemptStatus::InProgress->value)
            ->whereHas('exam', fn ($q) => $q->whereNotNull('duration_minutes'))
            ->get()
            ->filter(fn ($attempt) => $attempt->isExpired());
    }

    private function expiredBySession(): Collection
    {
        return ExamAttempt::with('exam')
            ->where('status', ExamAttemptStatus::InProgress->value)
            ->whereHas('exam', fn ($q) => $q->whereNotNull('session_started_at')->whereNotNull('session_duration_minutes'))
            ->get()
            ->filter(function ($attempt) {
                $exam = $attempt->exam;
                $sessionDeadline = $exam->session_started_at->getTimestamp() + ($exam->session_duration_minutes * 60);

                return now()->getTimestamp() >= $sessionDeadline;
            });
    }
}
