<?php

use App\Models\SubscriptionPlan;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantTest extends TestCase
{
    use RefreshDatabase;

    private SuperAdmin $admin;

    /*
     * setUp authenticates a super admin for every test in this class.
     * All tenant management endpoints require a valid Sanctum token so this
     * avoids repeating actingAs() in every test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = SuperAdmin::factory()->create();
        $this->actingAs($this->admin, "sanctum");
    }

    /*
     * List tenants — verifies pagination shape and that all created tenants appear.
     * Checks data key specifically because Laravel pagination wraps results.
     */
    #[Test]
    public function can_list_all_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $response = $this->getJson("/api/super-admin/tenants")->assertStatus(
            200,
        );

        expect($response->json("data"))->toHaveCount(3);
    }

    #[Test]
    public function list_tenants_returns_empty_data_when_no_tenants_exist(): void
    {
        $response = $this->getJson("/api/super-admin/tenants")->assertStatus(
            200,
        );

        expect($response->json("data"))->toHaveCount(0);
    }

    #[Test]
    public function can_filter_tenants_by_search_query(): void
    {
        Tenant::factory()->create(["name" => "Greenfield Academy"]);
        Tenant::factory()->create(["name" => "Kings College"]);

        $response = $this->getJson(
            "/api/super-admin/tenants?search=greenfield",
        )->assertStatus(200);

        expect($response->json("data"))->toHaveCount(1);
        expect($response->json("data.0.name"))->toBe("Greenfield Academy");
    }

    #[Test]
    public function can_filter_tenants_by_subscription_status(): void
    {
        Tenant::factory()
            ->count(2)
            ->create(["subscription_status" => "trial"]);
        Tenant::factory()->suspended()->create();

        $response = $this->getJson(
            "/api/super-admin/tenants?status=suspended",
        )->assertStatus(200);

        expect($response->json("data"))->toHaveCount(1);
    }

    /*
     * Create tenant — only tests the API response and DB record.
     * Does not test database provisioning (that's covered in TenantProvisioningTest).
     * Domain is auto-generated from slug so we don't pass it in the request.
     */
    #[Test]
    public function can_create_a_new_tenant(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson("/api/super-admin/tenants", [
            "name" => "Test School",
            "email" => "info@testschool.com",
            "plan_id" => $plan->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath("name", "Test School")
            ->assertJsonPath("slug", "test-school");
    }

    #[Test]
    public function create_tenant_auto_generates_slug_from_name(): void
    {
        $this->postJson("/api/super-admin/tenants", [
            "name" => "Kings College Lagos",
        ])
            ->assertStatus(201)
            ->assertJsonPath("slug", "kings-college-lagos");
    }

    #[Test]
    public function create_tenant_slug_is_unique_when_name_conflicts(): void
    {
        // Create tenant with id and slug both set to 'test-school'
        Tenant::factory()->create([
            "id" => "test-school",
            "slug" => "test-school",
            "name" => "Test School",
        ]);

        $response = $this->postJson("/api/super-admin/tenants", [
            "name" => "Test School",
        ])->assertStatus(201);

        expect($response->json("slug"))->toBe("test-school-1");
    }

    #[Test]
    public function create_tenant_fails_with_missing_name(): void
    {
        $this->postJson("/api/super-admin/tenants", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(["name"]);
    }

    /*
     * View tenant — verifies the show endpoint returns tenant with domains relationship.
     */
    #[Test]
    public function can_view_a_single_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->getJson("/api/super-admin/tenants/{$tenant->id}")
            ->assertStatus(200)
            ->assertJsonPath("id", $tenant->id)
            ->assertJsonStructure(["id", "name", "slug", "domains"]);
    }

    #[Test]
    public function view_tenant_returns_404_for_non_existent_tenant(): void
    {
        $this->getJson(
            "/api/super-admin/tenants/non-existent-id",
        )->assertStatus(404);
    }

    /*
     * Update tenant — uses PATCH so only provided fields are updated.
     * Verifies both the response and the DB record are updated.
     */
    #[Test]
    public function can_update_tenant_details(): void
    {
        $tenant = Tenant::factory()->create(["name" => "Old Name"]);

        $this->patchJson("/api/super-admin/tenants/{$tenant->id}", [
            "name" => "New Name",
        ])
            ->assertStatus(200)
            ->assertJsonPath("name", "New Name");

        $this->assertDatabaseHas("tenants", [
            "id" => $tenant->id,
            "name" => "New Name",
        ]);
    }

    #[Test]
    public function update_tenant_returns_404_for_non_existent_tenant(): void
    {
        $this->patchJson("/api/super-admin/tenants/non-existent-id", [
            "name" => "New Name",
        ])->assertStatus(404);
    }

    /*
     * Suspend — sets is_active to false and subscription_status to suspended.
     * Both fields must change — checking only one would miss a partial update bug.
     */
    #[Test]
    public function can_suspend_an_active_tenant(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/suspend")
            ->assertStatus(200)
            ->assertJsonPath("message", "Tenant suspended successfully");

        $fresh = $tenant->fresh();
        expect($fresh->is_active)->toBeFalse();
        // Compare against the enum value, not a raw string
        expect($fresh->subscription_status->value)->toBe("suspended");
    }

    #[Test]
    public function suspending_an_already_suspended_tenant_returns_422(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/suspend")
            ->assertStatus(422)
            ->assertJsonPath("message", "Tenant is already suspended");
    }

    /*
     * Reinstate — reverses suspension. Mirrors the suspend tests for symmetry.
     */
    #[Test]
    public function can_reinstate_a_suspended_tenant(): void
    {
        $tenant = Tenant::factory()->suspended()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/reinstate")
            ->assertStatus(200)
            ->assertJsonPath("message", "Tenant reinstated successfully");

        $fresh = $tenant->fresh();
        expect($fresh->is_active)->toBeTrue();
        expect($fresh->subscription_status->value)->toBe("active");
    }

    #[Test]
    public function reinstating_a_non_suspended_tenant_returns_422(): void
    {
        $tenant = Tenant::factory()->active()->create();

        $this->postJson("/api/super-admin/tenants/{$tenant->id}/reinstate")
            ->assertStatus(422)
            ->assertJsonPath("message", "Tenant is not suspended");
    }

    /*
     * Soft delete — verifies the record is soft deleted not hard deleted.
     * The tenant DB itself is preserved until a hard delete is triggered.
     */
    #[Test]
    public function can_soft_delete_a_tenant(): void
    {
        $tenant = Tenant::factory()->create();

        $this->deleteJson("/api/super-admin/tenants/{$tenant->id}")
            ->assertStatus(200)
            ->assertJsonPath("message", "Tenant deleted successfully");

        $this->assertSoftDeleted("tenants", ["id" => $tenant->id]);
    }

    #[Test]
    public function delete_tenant_returns_404_for_non_existent_tenant(): void
    {
        $this->deleteJson(
            "/api/super-admin/tenants/non-existent-id",
        )->assertStatus(404);
    }

    #[Test]
    public function can_create_a_new_tenant_school_real_pipeline(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        $response = $this->postJson("/api/super-admin/tenants", [
            "name" => "Test School",
            "email" => "info@testschool.com",
            "plan_id" => $plan->id,
        ])
            ->assertStatus(201)
            ->assertJsonPath("name", "Test School")
            ->assertJsonPath("slug", "test-school");

        $tenant = Tenant::where("slug", "test-school")->first();
        expect($tenant)->not->toBeNull();
    }
}
