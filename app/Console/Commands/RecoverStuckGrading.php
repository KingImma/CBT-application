<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GradeExamAttemptJob;
use App\Models\Tenant;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Console\Command;

class RecoverStuckGrading extends Command
{
    protected $signature = 'exams:recover-stuck-grading
        {--dry-run : List stuck attempts without dispatching jobs}
        {--tenant= : Process only a specific tenant ID}';

    protected $description = 'Re-dispatch grading jobs for attempts stuck in Grading status';

    public function handle(): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->where('is_active', true)->get()
            : Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        $totalDispatched = 0;

        foreach ($tenants as $tenant) {
            $tenant->run(function () use (&$totalDispatched) {
                $stuck = ExamAttempt::where('status', 'grading')->get();

                if ($stuck->isEmpty()) {
                    $this->line('No stuck attempts found.');

                    return;
                }

                foreach ($stuck as $attempt) {
                    if ($this->option('dry-run')) {
                        $this->line("Would re-dispatch grading for attempt {$attempt->id}, exam {$attempt->exam_id}, student {$attempt->student_id}");

                        continue;
                    }

                    GradeExamAttemptJob::dispatch($attempt->id, (string) tenant('id'));
                    $totalDispatched++;
                }

                if (! $this->option('dry-run')) {
                    $this->line("Dispatched {$stuck->count()} grading job(s).");
                }
            });
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No jobs dispatched.');
        } else {
            $this->info("Dispatched {$totalDispatched} grading job(s) total.");
        }

        return self::SUCCESS;
    }
}
