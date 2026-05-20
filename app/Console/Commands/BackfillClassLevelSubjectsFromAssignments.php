<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillClassLevelSubjectsFromAssignments extends Command
{
    protected $signature = 'tenants:backfill-class-level-subjects
                           {--tenant= : Backfill a single tenant by slug}
                           {--dry-run : Preview without writing}';

    protected $description = 'Populate class_level_subject from existing teacher_subject_assignments for each tenant';

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

        foreach ($tenants as $tenant) {
            $this->info("\n▶ Tenant: {$tenant->name} ({$tenant->id})");

            try {
                tenancy()->initialize($tenant);
                $count = $this->backfillTenant($isDryRun);
                $this->info("  ✓ {$count} class_level_subject record(s) ".($isDryRun ? 'would be created' : 'created'));
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        $isDryRun
            ? $this->warn('Dry run complete. Re-run without --dry-run to apply.')
            : $this->info('Backfill complete.');

        return self::SUCCESS;
    }

    private function backfillTenant(bool $isDryRun): int
    {
        $pairs = DB::table('teacher_subject_assignments')
            ->select('subject_id', 'class_level_id')
            ->distinct()
            ->get();

        if ($pairs->isEmpty()) {
            $this->line('  → No teacher subject assignments found.');

            return 0;
        }

        $count = 0;

        foreach ($pairs as $pair) {
            $exists = DB::table('class_level_subject')
                ->where('class_level_id', $pair->class_level_id)
                ->where('subject_id', $pair->subject_id)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->line("  + class_level {$pair->class_level_id} ← subject {$pair->subject_id}");

            if (! $isDryRun) {
                DB::table('class_level_subject')->insert([
                    'id' => Str::uuid()->toString(),
                    'class_level_id' => $pair->class_level_id,
                    'subject_id' => $pair->subject_id,
                    'is_compulsory' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $count++;
        }

        return $count;
    }
}
