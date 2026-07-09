<?php

declare(strict_types=1);

namespace App\Data\Console\Commands;

use App\Actions\Tenants\Exam\Attempts\FinalizeAttempt;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Console\Command;

class AutoSubmitExpiredExams extends Command
{
    protected $signature = 'exams:auto-submit-expired';

    protected $description = 'Auto-submit expired exam attempts based on individual timer or global window end';

    public function __construct(
        private FinalizeAttempt $finalizeAction,
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
            $tenant->run(function () {
                // Note: Removed the whereHas('exam.duration_minutes') check so we
                // can also catch exams that only rely on window_end.
                ExamAttempt::with('exam')
                    ->where('status', ExamAttemptStatus::InProgress->value)
                    ->chunk(100, function ($attempts) {
                        foreach ($attempts as $attempt) {
                            $exam = $attempt->exam;
                            $windowClosed = $exam->window_end && now()->gte($exam->window_end);

                            // Skip if neither the timer nor the window has expired
                            if (! $attempt->isExpired() && ! $windowClosed) {
                                continue;
                            }

                            try {
                                $this->finalizeAction->execute($attempt);
                                $this->info("  Auto-submitted attempt {$attempt->id} for student {$attempt->student_id}");
                            } catch (\Exception $e) {
                                $this->error("  Failed to submit attempt {$attempt->id}: {$e->getMessage()}");
                            }
                        }
                    });
            });
        }

        return self::SUCCESS;
    }
}
