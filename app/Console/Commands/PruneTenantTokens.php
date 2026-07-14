<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Models\Tenant;

class PruneTenantTokens extends Command
{
    protected $signature = 'tenants:prune-expired-tokens {--hours=24 : Minimum hours since expiration before pruning}';

    protected $description = 'Prune expired Sanctum tokens from all tenant databases';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $cutoff = now()->subHours($hours);

        $this->info("Pruning tenant tokens expired before {$cutoff->toDateTimeString()}...");

        $tenants = Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        $totalPruned = 0;

        foreach ($tenants as $tenant) {
            try {
                $tenant->makeCurrent();

                $count = \DB::table('personal_access_tokens')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<', $cutoff)
                    ->delete();

                $totalPruned += $count;

                $this->line("[{$tenant->handle}] Pruned {$count} expired tokens.");

                $tenant->forgetCurrent();
            } catch (\Throwable $e) {
                Log::channel('slack')->error("Token pruning failed for tenant {$tenant->handle}", [
                    'tenant' => $tenant->handle,
                    'error' => $e->getMessage(),
                ]);

                $this->error("[{$tenant->handle}] Failed: {$e->getMessage()}");
            }
        }

        $this->info("Total tokens pruned across all tenants: {$totalPruned}");

        return self::SUCCESS;
    }
}
