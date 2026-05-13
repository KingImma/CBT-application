<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Tenants\Exam\ExamLifecycleAction;
use App\Actions\Tenants\Exam\ExamSessionAction;
use App\Models\Tenant\Exam;
use Illuminate\Console\Command;

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
        $this->info('Checking for expired exam sessions...');

        $expiredSessions = Exam::where('status', 'active')
            ->whereNotNull('scheduled_end')
            ->where('scheduled_end', '<=', now())
            ->get();

        $this->info("Found {$expiredSessions->count()} expired exam sessions.");

        foreach ($expiredSessions as $exam) {
            try {
                $this->lifecycleAction->endSession($exam, $this->sessionAction);
                $this->info("Ended session for exam '{$exam->title}' ({$exam->id})");
            } catch (\Exception $e) {
                $this->error("Failed to end session for exam {$exam->id}: {$e->getMessage()}");
            }
        }

        $this->info('Expired session check completed.');

        return self::SUCCESS;
    }
}
