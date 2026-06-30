<?php

// - Backfill command for existing schools — populates class_arm_subject
//   from the existing class_level_subject data
// - What: for every arm in every class level, copies the level's subjects to the arm
// - Why: existing tenants provisioned before this feature have no arm-subject records
// - Expected: run once per tenant (or all tenants) after deploying the migration
// - Alternative: let admins set it manually — worse UX, they'd have to redo everything
// @deprecated This is a one-time data migration command. Keep only if re-run is needed.

declare(strict_types=1);

namespace App\Data\Console\Commands;

use App\Models\Tenant;
use App\Models\Tenant\ClassLevel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BackfillArmSubjectsFromClassLevel extends Command
{
    protected $signature = 'tenants:backfill-arm-subjects
                              {--tenant= : Backfill a single tenant by slug}
                              {--dry-run : Preview without writing}';

    protected $description = 'Copy class-level subject allocations down to each arm for existing tenants';

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
                $this->info("  ✓ {$count} arm-subject record(s) ".($isDryRun ? 'would be created' : 'created'));
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
        $count = 0;

        // Get all class levels that have subjects and arms
        $classLevels = ClassLevel::with([
            'subjects',
            'classArms',
        ])->get();

        foreach ($classLevels as $level) {
            if ($level->subjects->isEmpty() || $level->classArms->isEmpty()) {
                continue;
            }

            foreach ($level->classArms as $arm) {
                // Skip arms that already have subjects assigned
                $alreadyHasSubjects = DB::table('class_arm_subject')
                    ->where('class_arm_id', $arm->id)
                    ->exists();

                if ($alreadyHasSubjects) {
                    $this->line("  → {$level->name} {$arm->name}: already has subjects, skipping.");

                    continue;
                }

                foreach ($level->subjects as $subject) {
                    $this->line("  + {$level->name} {$arm->name} ← {$subject->name}");

                    if (! $isDryRun) {
                        DB::table('class_arm_subject')->insertOrIgnore([
                            'id' => Str::uuid()->toString(),
                            'class_arm_id' => $arm->id,
                            'subject_id' => $subject->id,
                            'is_compulsory' => (bool) $subject->pivot->is_compulsory,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $count++;
                    } else {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }
}
