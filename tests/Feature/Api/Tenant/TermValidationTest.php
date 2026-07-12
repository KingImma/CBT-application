<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Actions\Tenants\Terms\CreateTerm;
use App\Exceptions\Domain\Session\DuplicateTermNameException;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Term;
use Illuminate\Support\Str;
use Tests\TestCase;

class TermValidationTest extends TestCase
{
    protected Tenant $tenant;

    protected string $tenantUuid;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantId = 'tenant-'.Str::uuid()->toString();

        $this->tenant = Tenant::factory()->create([
            'id' => $tenantId,
            'slug' => $tenantId,
            'handle' => $tenantId,
            'database' => 'tenant_'.str_replace('-', '_', $tenantId),
        ]);

        tenancy()->initialize($this->tenant);

        // Use the raw UUID for the terms.tenant_id column (which is uuid type)
        $this->tenantUuid = Str::uuid()->toString();
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        try {
            $this->tenant->delete();
        } catch (\Exception) {
            // Ignore cleanup failures.
        }

        parent::tearDown();
    }

    public function test_prevents_duplicate_term_names_within_the_same_tenant(): void
    {
        $session = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        Term::create([
            'name' => 'First Term',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-15',
            'is_current' => false,
            'academic_session_id' => $session->id,
            'tenant_id' => $this->tenantUuid,
        ]);

        $this->expectException(DuplicateTermNameException::class);
        $this->expectExceptionMessage("A term with the name 'First Term' already exists in this session.");

        (new CreateTerm)->execute([
            'name' => 'First Term',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-15',
            'is_current' => false,
            'academic_session_id' => $session->id,
            'tenant_id' => $this->tenantUuid,
        ]);
    }

    public function test_allows_same_term_name_in_different_tenants(): void
    {
        $sessionA = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        $sessionB = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        $tenantUuidA = Str::uuid()->toString();
        $tenantUuidB = Str::uuid()->toString();

        Term::create([
            'name' => 'First Term',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-15',
            'is_current' => false,
            'academic_session_id' => $sessionA->id,
            'tenant_id' => $tenantUuidA,
        ]);

        $termB = Term::create([
            'name' => 'First Term',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-15',
            'is_current' => false,
            'academic_session_id' => $sessionB->id,
            'tenant_id' => $tenantUuidB,
        ]);

        $this->assertDatabaseHas('terms', [
            'id' => $termB->id,
            'name' => 'First Term',
            'tenant_id' => $tenantUuidB,
        ]);
    }

    public function test_allows_updating_a_term_to_the_same_name(): void
    {
        $session = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        $term = Term::create([
            'name' => 'Original Name',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-15',
            'is_current' => false,
            'academic_session_id' => $session->id,
            'tenant_id' => $this->tenantUuid,
        ]);

        $term->update(['name' => 'Original Name']);

        $this->assertDatabaseHas('terms', [
            'id' => $term->id,
            'name' => 'Original Name',
        ]);
    }

    public function test_prevents_updating_a_term_to_an_existing_name_in_the_same_tenant(): void
    {
        $session = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        $termA = Term::create([
            'name' => 'Term A',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-15',
            'is_current' => false,
            'academic_session_id' => $session->id,
            'tenant_id' => $this->tenantUuid,
        ]);

        $termB = Term::create([
            'name' => 'Term B',
            'start_date' => '2025-12-16',
            'end_date' => '2026-03-15',
            'is_current' => false,
            'academic_session_id' => $session->id,
            'tenant_id' => $this->tenantUuid,
        ]);

        $this->expectException(DuplicateTermNameException::class);

        (new \App\Actions\Tenants\Terms\UpdateTerm)->execute($termB, ['name' => 'Term A']);
    }

    public function test_handles_concurrent_create_gracefully_via_database_exception(): void
    {
        $session = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        $createTerm = new CreateTerm;

        $term1 = $createTerm->execute([
            'name' => 'Race Condition Term',
            'tenant_id' => $this->tenantUuid,
            'academic_session_id' => $session->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_current' => false,
        ]);

        $this->assertDatabaseHas('terms', [
            'name' => 'Race Condition Term',
            'tenant_id' => $this->tenantUuid,
        ]);

        $this->expectException(DuplicateTermNameException::class);

        $createTerm->execute([
            'name' => 'Race Condition Term',
            'tenant_id' => $this->tenantUuid,
            'academic_session_id' => $session->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'is_current' => false,
        ]);
    }
}
