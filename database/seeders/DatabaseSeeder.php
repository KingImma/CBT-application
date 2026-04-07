<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Central seeders only — never run in tenant context.
        // Tenant data is seeded by TenantDatabaseSeeder via the
        // TenantCreated event pipeline (see TenancyServiceProvider).
        $this->call([SubscriptionPlanSeeder::class, AdminUserSeeder::class]);
    }
}
