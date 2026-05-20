<?php

// - Artisan command to backfill handle column for existing tenants
// - Reads slug, derives a unique handle, writes it back to the central DB
// - Command chosen over raw SQL so you can dry-run and inspect before committing
// - Deliverable: all tenant rows have a populated handle, ready for subdomain routing
// - Alternative: direct SQL UPDATE — faster but no visibility or dry-run support

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class BackfillTenantHandles extends Command
{
    protected $signature = 'tenants:backfill-handles {--dry-run : Preview changes without writing to DB}';

    protected $description = 'Generate handles for existing tenants that do not have one';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $tenants = Tenant::whereNull('handle')
            ->orWhere('handle', '')
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('All tenants already have handles. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Found {$tenants->count()} tenant(s) without a handle.");
        $isDryRun && $this->warn('DRY RUN — no changes will be written.');

        $rows = [];

        foreach ($tenants as $tenant) {
            $handle = $this->deriveUniqueHandle($tenant->slug, $tenant->id);

            $rows[] = [
                'tenant_id' => $tenant->id,
                'slug' => $tenant->slug,
                'handle' => $handle,
                'action' => $isDryRun ? 'would set' : 'set',
            ];

            if (! $isDryRun) {
                $tenant->update(['handle' => $handle]);
            }
        }

        $this->table(['Tenant ID', 'Slug', 'Handle', 'Action'], $rows);

        $isDryRun
            ? $this->warn('Dry run complete. Run without --dry-run to apply.')
            : $this->info('Backfill complete.');

        return self::SUCCESS;
    }

    /**
     * Derives a handle from the slug.
     * If the slug is already taken as a handle by another tenant, appends a counter.
     */
    private function deriveUniqueHandle(string $slug, string $excludeId): string
    {
        $base = Str::lower(Str::substr($slug, 0, 3)); // "kcl", "abc"
        $handle = $base;
        $counter = 1;

        while (
            Tenant::where('handle', $handle)
                ->where('id', '!=', $excludeId)
                ->exists()
        ) {
            $handle = $base.$counter++; // "kcl1", "kcl2"
        }

        return $handle;
    }
}
