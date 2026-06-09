<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 *
 * Tenant factory does NOT trigger the full stancl provisioning pipeline
 * (CreateDatabase → MigrateDatabase → SeedDatabase) because that would
 * create real Postgres databases on every test run, making tests slow and
 * leaving orphaned DBs. Instead it creates the tenant record only.
 * Tests that need the full pipeline should use the TenantProvisioningTest suite.
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = fake()->company();
        $slug = Str::slug($name);

        return [
            'id' => $slug,
            'name' => $name,
            'slug' => $slug,
            'handle' => $slug,
            'database' => 'tenant_'.str_replace('-', '_', $slug),
            'plan_id' => SubscriptionPlan::factory(),
            'subscription_status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
            'is_active' => true,
        ];
    }

    public function suspended(): static
    {
        return $this->state([
            'subscription_status' => 'suspended',
            'is_active' => false,
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'subscription_status' => 'active',
            'is_active' => true,
        ]);
    }
}
