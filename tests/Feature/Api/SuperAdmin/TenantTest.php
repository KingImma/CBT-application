<?php

namespace Tests\Feature\Api\SuperAdmin;

use App\Models\SubscriptionPlan;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase {
        beginDatabaseTransaction as baseBeginDatabaseTransaction;
    }

    private SuperAdmin $admin;

    protected function beginDatabaseTransaction(): void
    {
        // No-op: CREATE DATABASE cannot run inside a transaction block.
        // Data isolation is handled by setUp/tearDown.
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::factory()->create();
        $this->actingAs($this->admin, 'super_admin');
    }

    protected function tearDown(): void
    {
        // Ensure we are on the central connection before doing any DB work.
        // The previous test may have left tenancy initialized, which switches
        // the pgsql connection to the tenant DB — you cannot DROP a database
        // while connected to it.
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // Suppress TenantDeleted → DeleteDatabase (no IF EXISTS) by wrapping
        // all tenant cleanup inside withoutEvents.
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
    public function can_list_all_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $response = $this->getJson('/api/super-admin/tenants')->assertStatus(200);

        expect($response->json('data'))->toHaveCount(3);
    }

    #[Test]
    public function list_tenants_returns_empty_data_when_no_tenants_exist(): void
    {
        $response = $this->getJson('/api/super-admin/tenants')->assertStatus(200);

        expect($response->json('data'))->toHaveCount(0);
    }

    #[Test]
    public function can_filter_tenants_by_search_query(): void
    {
        Tenant::factory()->create(['name' => 'Greenfield Academy']);
        Tenant::factory()->create(['name' => 'Kings College']);

        $response = $this->getJson(
            '/api/super-admin/tenants?search=greenfield',
        )->assertStatus(200);

        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.name'))->toBe('Greenfield Academy');
    }

    #[Test]
    public function can_filter_tenants_by_subscription_status(): void
    {
        Tenant::factory()
            ->count(2)
            ->create(['subscription_status' => 'trial']);
        Tenant::factory()->suspended()->create();

        $response = $this->getJson(
            '/api/super-admin/tenants?status=suspended',
        )->assertStatus(200);

        expect($response->json('data'))->toHaveCount(1);
    }

    #[Test]
    public function can_create_a_new_tenant(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson('/api/super-admin/tenants', array_merge([
            'name' => 'Test School',
            'email' => 'info@testschool.com',
            'plan_id' => $plan->id,
        ], $this->adminFields()))
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Test School')
            ->assertJsonPath('data.slug', 'test-school');
    }

    #[Test]
    public function create_tenant_auto_generates_slug_from_name(): void
    {
        $this->postJson('/api/super-admin/tenants', array_merge([
            'name' => 'Kings College Lagos',
        ], $this->adminFields()))
            ->assertStatus(201)
            ->assertJsonPath('data.slug', 'kings-college-lagos');
    }

    #[Test]
    public function create_tenant_slug_is_unique_when_name_conflicts(): void
    {
        Tenant::factory()->create([
            'id' => 'test-school',
            'slug' => 'test-school',
            'name' => 'Test School',
        ]);

        $this->postJson('/api/super-admin/tenants', array_merge([
            'name' => 'Test School',
        ], $this->adminFields()))
            ->assertStatus(409)
            ->assertJsonPath('message', "The subdomain 'test-school' is already taken. Please choose a different school name.");
    }

    #[Test]
    public function create_tenant_fails_with_missing_name(): void
    {
        $this->postJson('/api/super-admin/tenants', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function can_view_a_single_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/super-admin/tenants/{$tenant->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $tenant->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'slug', 'domains']]);
    }

    #[Test]
    public function view_tenant_returns_404_for_non_existent_tenant(): void
    {
        $this->getJson(
            '/api/super-admin/tenants/non-existent-id',
        )->assertStatus(404);
    }

    #[Test]
    public function can_update_tenant_details(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Old Name']);

        $this->patchJson("/api/super-admin/tenants/{$tenant->id}", [
            'name' => 'New Name',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'New Name',
        ]);
    }

    #[Test]
    public function update_tenant_returns_404_for_non_existent_tenant(): void
    {
        $this->patchJson('/api/super-admin/tenants/non-existent-id', [
            'name' => 'New Name',
        ])->assertStatus(404);
    }

    #[Test]
    public function can_suspend_an_active_tenant(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/suspend")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Tenant suspended successfully.');

        $fresh = $tenant->fresh();
        expect($fresh->is_active)->toBeFalse();
        expect($fresh->subscription_status->value)->toBe('suspended');
    }

    #[Test]
    public function suspending_an_already_suspended_tenant_returns_422(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/suspend")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tenant is already suspended');
    }

    #[Test]
    public function can_reinstate_a_suspended_tenant(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/reinstate")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Tenant reinstated successfully.');

        $fresh = $tenant->fresh();
        expect($fresh->is_active)->toBeTrue();
        expect($fresh->subscription_status->value)->toBe('active');
    }

    #[Test]
    public function reinstating_a_non_suspended_tenant_returns_422(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/reinstate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tenant is not suspended');
    }

    #[Test]
    public function can_soft_delete_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->deleteJson("/api/super-admin/tenants/{$tenant->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Tenant deleted successfully');

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    #[Test]
    public function delete_tenant_returns_404_for_non_existent_tenant(): void
    {
        $this->deleteJson(
            '/api/super-admin/tenants/non-existent-id',
        )->assertStatus(404);
    }

    #[Test]
    public function can_create_a_new_tenant_school_real_pipeline(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $response = $this->postJson('/api/super-admin/tenants', array_merge([
            'name' => 'Test School',
            'email' => 'info@testschool.com',
            'plan_id' => $plan->id,
        ], $this->adminFields()))
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Test School')
            ->assertJsonPath('data.slug', 'test-school');

        $tenant = Tenant::where('slug', 'test-school')->first();
        expect($tenant)->not->toBeNull();
    }
}
