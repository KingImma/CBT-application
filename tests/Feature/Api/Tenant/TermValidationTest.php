<?php

namespace Tests\Feature\Api\Tenant;

use App\Actions\Tenants\Terms\CreateTerm;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TermValidationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed a tenant for testing
        $this->tenant = Tenant::factory()->create([
            'id' => 'test-tenant',
        ]);

        // Activate the tenant for the test
        $this->app->instance('tenancy.tenant', $this->tenant);
    }

    /** @test */
    public function it_prevents_duplicate_term_names_within_the_same_tenant()
    {
        // Create an academic session for the tenant
        $session = AcademicSession::factory()->for($this->tenant)->create();

        // Create first term
        Term::factory()->for($this->tenant)->for($session)->create([
            'name' => 'First Term',
        ]);

        // Attempt to create a second term with the same name in the same tenant and session
        $this->expectException(ValidationException::class);

        Term::factory()->for($this->tenant)->for($session)->make([
            'name' => 'First Term',
        ])->save(); // This should throw a ValidationException due to our service

        // Alternatively, we can test via the controller or service directly.
        // Let's test the service directly for clarity.
    }

    /** @test */
    public function it_allows_same_term_name_in_different_tenants()
    {
        // Create two tenants
        $tenantA = Tenant::factory()->create(['id' => 'tenant-a']);
        $tenantB = Tenant::factory()->create(['id' => 'tenant-b']);

        // Create an academic session for each tenant
        $sessionA = AcademicSession::factory()->for($tenantA)->create();
        $sessionB = AcademicSession::factory()->for($tenantB)->create();

        // Create a term in tenant A
        Term::factory()->for($tenantA)->for($sessionA)->create([
            'name' => 'First Term',
        ]);

        // Create a term in tenant B with the same name - should pass
        $termB = Term::factory()->for($tenantB)->for($sessionB)->create([
            'name' => 'First Term',
        ]);

        $this->assertDatabaseHas('terms', [
            'id' => $termB->id,
            'name' => 'First Term',
            'tenant_id' => $tenantB->id,
        ]);
    }

    /** @test */
    public function it_allows_updating_a_term_to_the_same_name()
    {
        // Create a tenant and session
        $tenant = Tenant::factory()->create(['id' => 'tenant-x']);
        $session = AcademicSession::factory()->for($tenant)->create();

        // Create a term
        $term = Term::factory()->for($tenant)->for($session)->create([
            'name' => 'Original Name',
        ]);

        // Update the term to the same name (should pass)
        $term->update([
            'name' => 'Original Name',
        ]);

        $this->assertDatabaseHas('terms', [
            'id' => $term->id,
            'name' => 'Original Name',
        ]);
    }

    /** @test */
    public function it_prevents_updating_a_term_to_an_existing_name_in_the_same_tenant()
    {
        // Create a tenant and session
        $tenant = Tenant::factory()->create(['id' => 'tenant-y']);
        $session = AcademicSession::factory()->for($tenant)->create();

        // Create two terms
        $termA = Term::factory()->for($tenant)->for($session)->create([
            'name' => 'Term A',
        ]);
        $termB = Term::factory()->for($tenant)->for($session)->create([
            'name' => 'Term B',
        ]);

        // Attempt to update termB to have the same name as termA
        $this->expectException(ValidationException::class);

        $termB->update([
            'name' => 'Term A',
        ]);
    }

    /** @test */
    public function it_handles_concurrent_create_gracefully_via_database_exception()
    {
        // This test simulates a race condition where two requests try to create the same term at the same time.
        // We'll simulate by trying to create two terms in a loop and catch the QueryException.

        $tenant = Tenant::factory()->create(['id' => 'tenant-z']);
        $session = AcademicSession::factory()->for($tenant)->create();

        // We'll attempt to create two terms with the same name in quick succession.
        // The first one should succeed, the second one should fail with a duplicate key error.
        // We'll use the service directly to catch the ValidationException that wraps the QueryException.

        $createTerm = new CreateTerm;

        $term1 = $createTerm->execute([
            'name' => 'Race Condition Term',
            'tenant_id' => $tenant->id,
            'academic_session_id' => $session->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_current' => false,
        ]);

        $this->assertDatabaseHas('terms', [
            'name' => 'Race Condition Term',
            'tenant_id' => $tenant->id,
        ]);

        // Now try to create a second term with the same name and tenant - should throw ValidationException
        $this->expectException(ValidationException::class);

        $termService->createTerm([
            'name' => 'Race Condition Term',
            'tenant_id' => $tenant->id,
            'academic_session_id' => $session->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_current' => false,
        ]);
    }
}
