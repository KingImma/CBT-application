<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ExamAttemptStatus;
use App\Domains\Exams\Jobs\GradeExamAttemptJob;
use App\Models\Tenant;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Console\Command;

class RecoverStuckGrading extends Command
{
    protected $signature = 'exams:recover-stuck-grading
        {--dry-run : List stuck attempts without dispatching jobs}';

    protected $description = 'Re-dispatches grading jobs for attempts stuck in Grading or Submitted status';

    public function handle(): int
    {
        $tenants = Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        $stuckStatuses = [
            ExamAttemptStatus::Submitted->value,
            ExamAttemptStatus::Grading->value,
        ];

        $totalDispatched = 0;

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($stuckStatuses, &$totalDispatched) {
                ExamAttempt::with('exam')
                    ->whereIn('status', $stuckStatuses)
                    ->chunk(100, function ($attempts) use (&$totalDispatched) {
                        foreach ($attempts as $attempt) {
                            $this->line("  Found attempt {$attempt->id} for exam {$attempt->exam_id} — status: {$attempt->status}");

                            if (! $this->option('dry-run')) {
                                GradeExamAttemptJob::dispatch(
                                    $attempt->id,
                                    (string) tenant('id'),
                                );
                                $totalDispatched++;
                            }
                        }
                    });
            });
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No jobs dispatched.');
        } else {
            $this->info("Dispatched {$totalDispatched} grading job(s).");
        }

        return self::SUCCESS;
    }
}
