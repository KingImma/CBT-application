<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Integration test for the full stancl tenant provisioning pipeline.
 *
 * IMPORTANT: This test creates real PostgreSQL databases and CANNOT use
 * RefreshDatabase. Run it in isolation — never as part of the main test suite.
 *
 * Run with:
 *   php artisan test tests/Integration/TenantProvisioningPipelineTest.php --env=testing
 *
 * Requires the test DB user to have CREATEDB privilege:
 *   ALTER USER educbt_user CREATEDB;
 */
class TenantProvisioningPipelineTest extends TestCase
{
    private const TENANT_SLUG = 'pipeline-test-school';
    private const TENANT_DB   = 'naijacbt_tenant_pipeline_test_school';

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up from any previous failed run before starting
        $this->cleanupTenant();
    }

    protected function tearDown(): void
    {
        // Always clean up after — orphaned DBs accumulate fast
        $this->cleanupTenant();

        parent::tearDown();
    }

    public function test_full_provisioning_pipeline_creates_tenant_database(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $tenant = Tenant::create([
            'id'                  => self::TENANT_SLUG,
            'name'                => 'Pipeline Test School',
            'slug'                => self::TENANT_SLUG,
            'database'            => self::TENANT_DB,
            'plan_id'             => $plan->id,
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(30),
            'is_active'           => true,
        ]);

        // Give the pipeline a moment — it runs synchronously in testing
        // but CreateDatabase needs a tick to complete
        $this->assertDatabaseExists($tenant->database()->getName());
    }

    public function test_provisioning_pipeline_runs_tenant_migrations(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        Tenant::create([
            'id'                  => self::TENANT_SLUG,
            'name'                => 'Pipeline Test School',
            'slug'                => self::TENANT_SLUG,
            'database'            => self::TENANT_DB,
            'plan_id'             => $plan->id,
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(30),
            'is_active'           => true,
        ]);

        $tenant = Tenant::find(self::TENANT_SLUG);
        tenancy()->initialize($tenant);

        $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
        $tableNames = array_column($tables, 'tablename');

        $this->assertContains('class_levels', $tableNames);
        $this->assertContains('subjects', $tableNames);
        $this->assertContains('school_settings', $tableNames);
        $this->assertContains('grading_scales', $tableNames);

        tenancy()->end();
    }

    public function test_provisioning_pipeline_seeds_default_data(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        Tenant::create([
            'id'                  => self::TENANT_SLUG,
            'name'                => 'Pipeline Test School',
            'slug'                => self::TENANT_SLUG,
            'database'            => self::TENANT_DB,
            'plan_id'             => $plan->id,
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(30),
            'is_active'           => true,
        ]);

        $tenant = Tenant::find(self::TENANT_SLUG);
        tenancy()->initialize($tenant);

        // Class levels
        $this->assertSame(6, DB::table('class_levels')->count());

        // Default grading scale
        $this->assertSame(1, DB::table('grading_scales')->where('is_default', true)->count());

        // Subjects
        $this->assertGreaterThanOrEqual(10, DB::table('subjects')->count());

        // School settings
        $this->assertTrue(
            DB::table('school_settings')->where('key', 'ca_weight')->exists()
        );
        $this->assertTrue(
            DB::table('school_settings')->where('key', 'exam_weight')->exists()
        );

        tenancy()->end();
    }

    public function test_deleting_tenant_removes_their_database(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $tenant = Tenant::create([
            'id'                  => self::TENANT_SLUG,
            'name'                => 'Pipeline Test School',
            'slug'                => self::TENANT_SLUG,
            'database'            => self::TENANT_DB,
            'plan_id'             => $plan->id,
            'subscription_status' => 'trial',
            'trial_ends_at'       => now()->addDays(30),
            'is_active'           => true,
        ]);

        $this->assertDatabaseExists($tenant->database()->getName());

        // Hard delete triggers stancl's DeleteDatabase job
        $tenant->forceDelete();

        $this->assertPostgresDatabaseMissing($tenant->database()->getName());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function cleanupTenant(): void
    {
        // Remove central tenant record if it exists
        Tenant::withTrashed()->where('id', self::TENANT_SLUG)->forceDelete();

        // Drop the tenant DB if it exists — handle gracefully since it may
        // not exist if the test failed before the pipeline ran
        try {
            DB::statement('DROP DATABASE IF EXISTS "' . self::TENANT_SLUG . '"');
        } catch (\Throwable) {
            // Silently ignore — DB may not exist
        }
    }

    private function assertDatabaseExists(string $dbName): void
    {
        $exists = DB::select(
            "SELECT 1 FROM pg_database WHERE datname = ?",
            [$dbName]
        );

        $this->assertNotEmpty($exists, "Expected database '{$dbName}' to exist.");
    }

    private function assertPostgresDatabaseMissing(string $dbName): void
    {
        $exists = DB::select(
            "SELECT 1 FROM pg_database WHERE datname = ?",
            [$dbName]
        );

        $this->assertEmpty($exists, "Expected database '{$dbName}' to not exist.");
    }
}
