<?php

declare(strict_types=1);

namespace Tests\Feature\Api\SuperAdmin;

use App\Actions\CreateTenantAction;
use App\Exceptions\TenantSlugAlreadyTakenException;
use App\Models\SubscriptionPlan;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    protected SuperAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::factory()->create();
        $this->actingAs($this->admin, 'sanctum');
    }

    // -------------------------------------------------------------------------
    // List
    // -------------------------------------------------------------------------

    #[Test]
    public function can_list_all_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $response = $this->getJson('/api/super-admin/tenants')->assertOk();

        $this->assertCount(3, $response->json('data'));
    }

    #[Test]
    public function list_tenants_returns_empty_data_when_no_tenants_exist(): void
    {
        $response = $this->getJson('/api/super-admin/tenants')->assertOk();

        $this->assertCount(0, $response->json('data'));
    }

    #[Test]
    public function can_filter_tenants_by_search_query(): void
    {
        Tenant::factory()->create(['id' => 'greenfield-academy', 'name' => 'Greenfield Academy', 'slug' => 'greenfield-academy']);
        Tenant::factory()->create(['id' => 'kings-college',      'name' => 'Kings College',      'slug' => 'kings-college']);

        $response = $this->getJson('/api/super-admin/tenants?search=greenfield')->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Greenfield Academy', $response->json('data.0.name'));
    }

    #[Test]
    public function can_filter_tenants_by_subscription_status(): void
    {
        Tenant::factory()->count(2)->create();
        Tenant::factory()->suspended()->create();

        $response = $this->getJson('/api/super-admin/tenants?status=suspended')->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    // -------------------------------------------------------------------------
    // Create
    // -------------------------------------------------------------------------

    #[Test]
    public function can_create_a_new_tenant(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson('/api/super-admin/tenants', [
            'name'    => 'Test School',
            'email'   => 'info@testschool.com',
            'plan_id' => $plan->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('name', 'Test School')
        ->assertJsonPath('slug', 'test-school');
    }

    #[Test]
    public function create_tenant_auto_generates_slug_from_name(): void
    {
        $this->postJson('/api/super-admin/tenants', [
            'name' => 'Kings College Lagos',
        ])
        ->assertStatus(201)
        ->assertJsonPath('slug', 'kings-college-lagos');
    }

    #[Test]
    public function create_tenant_fails_with_missing_name(): void
    {
        $this->postJson('/api/super-admin/tenants', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function create_tenant_fails_with_422_if_slug_is_already_taken(): void
    {
        // Create an existing tenant that will produce the same slug
        Tenant::factory()->create([
            'id'   => 'test-school',
            'slug' => 'test-school',
            'name' => 'Test School',
        ]);

        // Same name → same slug → should be blocked at the validation layer
        $this->postJson('/api/super-admin/tenants', [
            'name' => 'Test School',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function concurrent_slug_conflict_throws_exception_at_action_level(): void
    {
        // Simulates the race condition where two requests pass validation
        // simultaneously but only one succeeds the DB write.
        // The action-level guard catches this and throws a typed exception
        // rather than letting a UniqueConstraintViolationException bubble up.
        Tenant::factory()->create([
            'id'   => 'test-school',
            'slug' => 'test-school',
            'name' => 'Test School X',
        ]);

        $this->expectException(TenantSlugAlreadyTakenException::class);

        (new CreateTenantAction())->execute(['name' => 'Test School']);
    }

    // -------------------------------------------------------------------------
    // Show
    // -------------------------------------------------------------------------

    #[Test]
    public function can_view_a_single_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/super-admin/tenants/{$tenant->id}")
            ->assertOk()
            ->assertJsonPath('id', $tenant->id)
            ->assertJsonStructure(['id', 'name', 'slug', 'domains']);
    }

    #[Test]
    public function view_tenant_returns_404_for_non_existent_tenant(): void
    {
        $this->getJson('/api/super-admin/tenants/non-existent-id')
            ->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Update
    // -------------------------------------------------------------------------

    #[Test]
    public function can_update_tenant_details(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Old Name']);

        $this->patchJson("/api/super-admin/tenants/{$tenant->id}", [
            'name' => 'New Name',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'New Name');

        $this->assertDatabaseHas('tenants', [
            'id'   => $tenant->id,
            'name' => 'New Name',
        ]);
    }

    #[Test]
    public function update_tenant_returns_404_for_non_existent_tenant(): void
    {
        $this->patchJson('/api/super-admin/tenants/non-existent-id', [
            'name' => 'New Name',
        ])->assertNotFound();
    }

    // -------------------------------------------------------------------------
    // Suspend
    // -------------------------------------------------------------------------

    #[Test]
    public function can_suspend_an_active_tenant(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/suspend")
            ->assertOk()
            ->assertJsonPath('message', 'Tenant suspended successfully');

        $fresh = $tenant->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertEquals('suspended', $fresh->subscription_status->value);
    }

    #[Test]
    public function suspending_an_already_suspended_tenant_returns_422(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/suspend")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tenant is already suspended');
    }

    // -------------------------------------------------------------------------
    // Reinstate
    // -------------------------------------------------------------------------

    #[Test]
    public function can_reinstate_a_suspended_tenant(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/reinstate")
            ->assertOk()
            ->assertJsonPath('message', 'Tenant reinstated successfully');

        $fresh = $tenant->fresh();
        $this->assertTrue($fresh->is_active);
        $this->assertEquals('active', $fresh->subscription_status->value);
    }

    #[Test]
    public function reinstating_a_non_suspended_tenant_returns_422(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/reinstate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Tenant is not suspended');
    }

    // -------------------------------------------------------------------------
    // Delete
    // -------------------------------------------------------------------------

    #[Test]
    public function can_soft_delete_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->deleteJson("/api/super-admin/tenants/{$tenant->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Tenant deleted successfully');

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
    }

    #[Test]
    public function delete_tenant_returns_404_for_non_existent_tenant(): void
    {
        $this->deleteJson('/api/super-admin/tenants/non-existent-id')
            ->assertNotFound();
    }
}