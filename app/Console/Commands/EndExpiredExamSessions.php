<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\Exam\ExamSessionAction;
use App\Actions\Tenants\Exam\ExamSessionLifecycleAction;
use App\Models\Tenant;
use App\Models\Tenant\Exam;
use Illuminate\Console\Command;

class EndExpiredExamSessions extends Command
{
    protected $signature = 'exams:end-expired-sessions';

    protected $description = 'End exam sessions that have passed their scheduled end time';

    public function __construct(
        private ExamSessionLifecycleAction $sessionLifecycleAction,
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

                $expiredSessions = Exam::active()
                    ->whereNotNull('scheduled_end')
                    ->where('scheduled_end', '<=', now())
                    ->get();

                foreach ($expiredSessions as $exam) {
                    try {
                        $this->sessionLifecycleAction->endSession($exam, $this->sessionAction);
                        $this->info("  Ended session for exam '{$exam->title}' ({$exam->id})");
                    } catch (\Exception $e) {
                        $this->error("  Failed to end session for exam {$exam->id}: {$e->getMessage()}");
                    }
                }
            });
        }

        return self::SUCCESS;
    }
}
