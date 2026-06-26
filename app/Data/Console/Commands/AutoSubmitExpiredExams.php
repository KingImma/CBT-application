<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\Exam\FinalizeAttempt;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Console\Command;

class AutoSubmitExpiredExams extends Command
{
    protected $signature = 'exams:auto-submit-expired';

    protected $description = 'Auto-submit expired exam attempts based on individual timer';

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
                ExamAttempt::with('exam')
                    ->where('status', ExamAttemptStatus::InProgress->value)
                    ->whereHas('exam', fn ($q) => $q->whereNotNull('duration_minutes'))
                    ->chunk(100, function ($attempts) {
                        foreach ($attempts as $attempt) {
                            if (! $attempt->isExpired()) {
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
