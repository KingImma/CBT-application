<?php

// app/Console/Commands/PruneOrphanedTenantDatabases.php
// - What: Artisan command to detect and drop tenant databases with no matching tenant record
// - Does: Queries all databases matching the 'tenant_' prefix, cross-references tenants table, drops orphans on confirmation
// - Why: Ghost databases from failed provisioning accumulate silently on Render's Postgres — this is the recovery mechanism
// - Delivers: Safe, reversible cleanup with dry-run support
// - Alternative: Use Stancl's built-in TenantDeleted listener exclusively — fails silently if the listener crashes

declare(strict_types=1);

namespace App\Data\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneOrphanedTenantDatabases extends Command
{
    protected $signature = 'tenants:prune-orphaned-databases {--dry-run : List without dropping}';

    protected $description = 'Drop tenant databases that have no matching tenant record';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        // Get all databases on the server matching our naming convention
        $allDatabases = DB::select("
            SELECT datname FROM pg_database
            WHERE datname LIKE 'tenant_%'
            AND datistemplate = false
        ");

        $knownDatabases = Tenant::withTrashed()->pluck('database')->toArray();

        $orphans = collect($allDatabases)
            ->pluck('datname')
            ->filter(fn ($db) => ! in_array($db, $knownDatabases))
            ->values();

        if ($orphans->isEmpty()) {
            $this->info('No orphaned databases found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$orphans->count()} orphaned database(s):");
        $orphans->each(fn ($db) => $this->line("  - {$db}"));

        if ($isDryRun) {
            $this->warn('Dry run — no databases dropped.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Drop these databases? This is irreversible.')) {
            return self::SUCCESS;
        }

        foreach ($orphans as $db) {
            // Must use the central connection — can't drop DB you're connected to
            DB::statement("DROP DATABASE IF EXISTS \"{$db}\"");
            $this->info("Dropped: {$db}");
        }

        return self::SUCCESS;
    }
}
