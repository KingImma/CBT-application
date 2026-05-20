<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\Exam\ExamLifecycleAction;
use App\Actions\Tenants\Exam\ExamSessionAction;
use App\Models\Tenant\Exam;
use Illuminate\Console\Command;
use Spatie\Multitenancy\Models\Tenant;

class EndExpiredExamSessions extends Command
{
    protected $signature = 'exams:end-expired-sessions';

    protected $description = 'End exam sessions that have passed their scheduled end time';

    public function __construct(
        private ExamLifecycleAction $lifecycleAction,
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
            $tenant->makeCurrent();
            $this->info("Checking tenant: {$tenant->id}");

            $expiredSessions = Exam::where('status', 'active')
                ->whereNotNull('scheduled_end')
                ->where('scheduled_end', '<=', now())
                ->get();

            foreach ($expiredSessions as $exam) {
                try {
                    $this->lifecycleAction->endSession($exam, $this->sessionAction);
                    $this->info("  Ended session for exam '{$exam->title}' ({$exam->id})");
                } catch (\Exception $e) {
                    $this->error("  Failed to end session for exam {$exam->id}: {$e->getMessage()}");
                }
            }

            $tenant->forgetCurrent();
        }

        return self::SUCCESS;
    }
}
