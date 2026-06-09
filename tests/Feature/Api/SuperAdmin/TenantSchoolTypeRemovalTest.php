<?php

declare(strict_types=1);

namespace Tests\Feature\Api\SuperAdmin;

use App\Models\SubscriptionPlan;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantSchoolTypeRemovalTest extends TestCase
{
    use RefreshDatabase {
        beginDatabaseTransaction as baseBeginDatabaseTransaction;
    }

    private SuperAdmin $admin;

    protected function beginDatabaseTransaction(): void
    {
        // No-op: CREATE DATABASE cannot run inside a transaction block.
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::factory()->create();
        $this->actingAs($this->admin, 'super_admin');
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        Tenant::withoutEvents(function () {
            $centralPdo = DB::connection('pgsql')->getPdo();

            foreach (Tenant::all() as $tenant) {
                $dbName = $tenant->database()->getName();
                if ($dbName) {
                    try {
                        $centralPdo->exec(
                            'DROP DATABASE IF EXISTS "'.$dbName.'"'
                        );
                    } catch (\Throwable) {
                        // DB may not exist / cannot drop while connected
                    }
                }
            }

            Tenant::query()->forceDelete();
        });

        parent::tearDown();
    }

    private function adminFields(): array
    {
        return [
            'admin_first_name' => 'Admin',
            'admin_last_name' => 'User',
            'admin_email' => 'admin@school.com',
            'admin_password' => 'password123',
        ];
    }

    #[Test]
    public function tenant_can_be_created_without_school_type(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $response = $this->postJson('/api/super-admin/tenants', array_merge([
            'name' => 'Test School',
            'email' => 'info@testschool.com',
            'plan_id' => $plan->id,
        ], $this->adminFields()));

        $response->assertStatus(201);
        $response->assertJsonMissingPath('data.school_type');
    }

    #[Test]
    public function tenant_list_response_does_not_include_school_type(): void
    {
        Tenant::factory()->create();

        $response = $this->getJson('/api/super-admin/tenants');

        $response->assertStatus(200);
        foreach ($response->json('data') as $tenant) {
            $this->assertArrayNotHasKey('school_type', $tenant);
        }
    }

    #[Test]
    public function tenant_detail_response_does_not_include_school_type(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->getJson("/api/super-admin/tenants/{$tenant->id}");

        $response->assertStatus(200);
        $response->assertJsonMissingPath('data.school_type');
    }

    #[Test]
    public function tenant_update_works_without_school_type(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Old Name']);

        $response = $this->patchJson("/api/super-admin/tenants/{$tenant->id}", [
            'name' => 'New Name',
        ]);

        $response->assertStatus(200);
        $response->assertJsonMissingPath('data.school_type');
        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'New Name',
        ]);
    }
}
