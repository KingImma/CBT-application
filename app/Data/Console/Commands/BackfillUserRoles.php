<?php

declare(strict_types=1);

namespace App\Data\Console\Commands;

use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class BackfillUserRoles extends Command
{
    protected $signature = 'tenants:backfill-user-roles
                           {--tenant= : Backfill a single tenant by slug}
                           {--dry-run : Preview without writing}';

    protected $description = 'Assign Spatie roles to existing users missing them';

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

        $totalAssigned = 0;

        foreach ($tenants as $tenant) {
            $this->info("\n▶ Tenant: {$tenant->name} ({$tenant->id})");

            try {
                tenancy()->initialize($tenant);
                $count = $this->backfillTenant($isDryRun);
                $totalAssigned += $count;
                $this->info("  ✓ {$count} role(s) ".($isDryRun ? 'would be assigned' : 'assigned'));
            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        $this->info('Total roles '.($isDryRun ? 'to be assigned' : 'assigned').": {$totalAssigned}");

        $isDryRun
            ? $this->warn('Dry run complete. Re-run without --dry-run to apply.')
            : $this->info('Backfill complete.');

        return self::SUCCESS;
    }

    private function backfillTenant(bool $isDryRun): int
    {
        $assigned = 0;

        $studentRole = Role::where('name', RoleType::Student->value)->where('guard_name', 'tenant')->first();
        if ($studentRole) {
            User::join('student_profiles', 'users.id', '=', 'student_profiles.user_id')
                ->select('users.id')
                ->cursor()
                ->each(function ($user) use ($studentRole, $isDryRun, &$assigned) {
                    $userModel = User::find($user->id);
                    if (! $userModel->hasRole($studentRole)) {
                        if (! $isDryRun) {
                            $userModel->assignRole($studentRole);
                        }
                        $assigned++;
                    }
                });
        }

        $teacherRole = Role::where('name', RoleType::Teacher->value)->where('guard_name', 'tenant')->first();
        if ($teacherRole) {
            User::join('teacher_profiles', 'users.id', '=', 'teacher_profiles.user_id')
                ->select('users.id')
                ->cursor()
                ->each(function ($user) use ($teacherRole, $isDryRun, &$assigned) {
                    $userModel = User::find($user->id);
                    if (! $userModel->hasRole($teacherRole)) {
                        if (! $isDryRun) {
                            $userModel->assignRole($teacherRole);
                        }
                        $assigned++;
                    }
                });
        }

        $adminRole = Role::where('name', RoleType::SchoolAdmin->value)->where('guard_name', 'tenant')->first();
        if ($adminRole) {
            User::where('role', RoleType::SchoolAdmin->value)
                ->cursor()
                ->each(function ($user) use ($adminRole, $isDryRun, &$assigned) {
                    if (! $user->hasRole($adminRole)) {
                        if (! $isDryRun) {
                            $user->assignRole($adminRole);
                        }
                        $assigned++;
                    }
                });
        }

        return $assigned;
    }
}
