<?php

// @deprecated One-time data migration. Keep only if re-run is needed.
declare(strict_types=1);

namespace App\Data\Console\Commands;

use App\Models\Tenant;
use App\Models\Tenant\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class BackfillTeacherPasswords extends Command
{
    protected $signature = 'tenants:backfill-teacher-passwords
                           {--tenant= : Backfill a single tenant by slug}
                           {--dry-run : Preview without writing}';

    protected $description = 'Reset all teacher passwords to the default for each tenant';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $tenantSlug = $this->option('tenant');

        $query = Tenant::query();
        if ($tenantSlug) {
            $query->where('id', $tenantSlug);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found.');

            return self::FAILURE;
        }

        $isDryRun && $this->warn('DRY RUN — no changes will be written.');
        $this->info("Processing {$tenants->count()} tenant(s)...");

        $totalUpdated = 0;

        foreach ($tenants as $tenant) {
            $this->info("\n▶ Tenant: {$tenant->name} ({$tenant->id})");

            try {
                tenancy()->initialize($tenant);
                $count = $this->backfillTenant($isDryRun);
                $totalUpdated += $count;
                $this->info("  ✓ {$count} teacher(s) ".($isDryRun ? 'would be updated' : 'updated'));
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        $this->info('Total teachers '.($isDryRun ? 'to be updated' : 'updated').": {$totalUpdated}");

        $isDryRun
            ? $this->warn('Dry run complete. Re-run without --dry-run to apply.')
            : $this->info('Backfill complete.');

        return self::SUCCESS;
    }

    private function backfillTenant(bool $isDryRun): int
    {
        $defaultPassword = 'teach12345';
        $hashed = Hash::make($defaultPassword);

        $count = User::role('teacher')->count();

        if ($count === 0) {
            $this->line('  → No teachers found.');

            return 0;
        }

        if (! $isDryRun) {
            User::role('teacher')
                ->where('password', '!=', $hashed)
                ->update(['password' => $hashed]);
        }

        return $count;
    }
}
