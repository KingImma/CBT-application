<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Assessments\Support\BackfillSubmissionCompletion;
use App\Models\Tenant;
use Illuminate\Console\Command;

class BackfillSubmissionCompletionCommand extends Command
{
    protected $signature = 'assessments:mark-submissions-completed
                           {--tenant= : Backfill a single tenant by ID}';

    protected $description = 'Mark submissions completed for exams that finished before the ExamCompleted chain shipped. Idempotent.';

    public function handle(): int
    {
        $query = Tenant::query();

        if ($tenantId = $this->option('tenant')) {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No matching tenants found.');

            return self::SUCCESS;
        }

        $this->info("Checking {$tenants->count()} tenant(s)...");

        $backfilled = 0;
        $failures = 0;

        foreach ($tenants as $tenant) {
            $this->line("▶ Tenant: {$tenant->name} ({$tenant->id})");

            try {
                tenancy()->initialize($tenant);

                $updated = (new BackfillSubmissionCompletion)->upgrade();

                $backfilled += $updated;
                $this->info("  + Completed {$updated} submission(s).");
            } catch (\Throwable $e) {
                $failures++;
                $this->error("  ✗ Failed: {$e->getMessage()}");
            } finally {
                tenancy()->end();
            }
        }

        $this->info("Completed {$backfilled} submission(s) across {$tenants->count()} tenant(s). Failed: {$failures}.");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
