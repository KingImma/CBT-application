<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

abstract class BootstrapsTenantCommand extends Command
{
    protected ?string $tenantOption = null;

    public function handle(): int
    {
        $query = $this->buildTenantQuery();

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        $this->info("Processing {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant);
        }

        return self::SUCCESS;
    }

    protected function buildTenantQuery(): Tenant|Collection
    {
        $query = Tenant::query();

        if ($this->hasOption('tenant') && $this->option('tenant')) {
            $query->where('id', $this->option('tenant'));
        }

        if ($this->hasOption('active-only') && $this->option('active-only') === false) {
            // Allow all tenants including inactive
        } else {
            $query->where('is_active', true);
        }

        return $query;
    }

    protected function runForTenant(Tenant $tenant, callable $callback): void
    {
        $this->info("▶ Tenant: {$tenant->name} ({$tenant->id})");

        try {
            tenancy()->initialize($tenant);
            $callback($tenant);
        } catch (\Throwable $e) {
            $this->error("  ✗ Failed: {$e->getMessage()}");
        } finally {
            tenancy()->end();
        }
    }

    abstract protected function processTenant(Tenant $tenant): void;
}
