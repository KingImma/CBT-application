<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\Exam\ExamLifecycleAction;
use App\Enums\ExamStatus;
use App\Models\Tenant;
use App\Models\Tenant\Exam;
use Illuminate\Console\Command;

class ActivateScheduledExams extends Command
{
    protected $signature = 'exams:activate-scheduled';

    protected $description = 'Auto-activate exams whose scheduled_start has passed';

    public function __construct(
        private ExamLifecycleAction $lifecycleAction,
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

                $scheduledExams = Exam::where('status', ExamStatus::Scheduled->value)
                    ->whereNotNull('scheduled_start')
                    ->where('scheduled_start', '<=', now())
                    ->get();

                foreach ($scheduledExams as $exam) {
                    try {
                        $this->lifecycleAction->startSession($exam);
                        $this->info("  Activated exam '{$exam->title}' ({$exam->id})");
                    } catch (\Exception $e) {
                        $this->error("  Failed to activate exam {$exam->id}: {$e->getMessage()}");
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}
