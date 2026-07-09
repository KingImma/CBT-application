<?php

declare(strict_types=1);

namespace App\Data\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillDefaultGradingScale extends Command
{
    protected $signature = 'tenants:backfill-grading-scale
                           {--tenant= : Backfill a single tenant by slug}
                           {--dry-run : Preview without writing}';

    protected $description = 'Ensure every tenant has a default grading scale, so grades stop showing N/A';

    private const DEFAULT_GRADES = [
        ['label' => 'A', 'min_score' => 70, 'max_score' => 100, 'remark' => 'Excellent'],
        ['label' => 'B', 'min_score' => 60, 'max_score' => 69,  'remark' => 'Very Good'],
        ['label' => 'C', 'min_score' => 50, 'max_score' => 59,  'remark' => 'Good'],
        ['label' => 'D', 'min_score' => 45, 'max_score' => 49,  'remark' => 'Pass'],
        ['label' => 'E', 'min_score' => 40, 'max_score' => 44,  'remark' => 'Fair'],
        ['label' => 'F', 'min_score' => 0,  'max_score' => 39,  'remark' => 'Fail'],
    ];

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $tenantSlug = $this->option('tenant');

        $query = Tenant::query();
        if ($tenantSlug) {
            $query->where('id', $tenantSlug);
        }

        $tenants = $query->get();
        $fixed = 0;

        foreach ($tenants as $tenant) {
            $this->info("▶ Tenant: {$tenant->name}");

            try {
                tenancy()->initialize($tenant);

                $hasDefault = DB::table('grading_scales')->where('is_default', true)->exists();

                if ($hasDefault) {
                    $this->line('  → Already has a default scale, skipping.');
                    continue;
                }

                $this->line('  + Creating default A-F grading scale'.($isDryRun ? ' (dry run)' : ''));

                if (! $isDryRun) {
                    DB::table('grading_scales')->insert([
                        'id' => Str::uuid()->toString(),
                        'name' => 'Standard A-F',
                        'is_default' => true,
                        'grades' => json_encode(self::DEFAULT_GRADES),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $fixed++;
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
            } finally {
                tenancy()->end();
            }
        }

        $this->info(($isDryRun ? 'Would fix' : 'Fixed').": {$fixed} tenant(s).");

        return self::SUCCESS;
    }
}
