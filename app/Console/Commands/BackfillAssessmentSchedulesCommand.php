<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Assessments\Support\BackfillAssessmentSchedules;
use App\Models\Tenant;
use Illuminate\Console\Command;

class BackfillAssessmentSchedulesCommand extends Command
{
    protected $signature = 'tenants:ensure-assessment-schedule-format
                           {--tenant= : Upgrade a single tenant by ID}';

    protected $description = 'Bring every tenant database to the global-assessment format (class bindings on schedules). Idempotent; heals tenants whose recorded migrations can never re-run.';

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

        $upgraded = 0;
        $failures = 0;

        foreach ($tenants as $tenant) {
            $this->line("▶ Tenant: {$tenant->name} ({$tenant->id})");

            try {
                tenancy()->initialize($tenant);

                $backfill = new BackfillAssessmentSchedules;

                if (! $backfill->isLegacyFormat()) {
                    $this->line('  ✓ Already on the global-assessment format.');

                    continue;
                }

                $backfill->upgrade();
                $upgraded++;
                $this->info('  + Upgraded to the occurrence model.');
            } catch (\Throwable $e) {
                $failures++;
                $this->error("  ✗ Failed: {$e->getMessage()}");
            } finally {
                tenancy()->end();
            }
        }

        $this->info("Upgraded: {$upgraded} tenant(s), already current: ".($tenants->count() - $upgraded - $failures).' tenant(s), failed: '.$failures.'.');

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }
}
