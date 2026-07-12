<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Actions\Tenants\Sessions\CreateSession;
use App\Actions\Tenants\Sessions\UpdateSession;
use App\Exceptions\Domain\Session\DuplicateSessionNameException;
use App\Exceptions\Domain\Session\SessionDateRangeOverlapException;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicSessionValidationTest extends TestCase
{
    protected Tenant $tenant;

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

    public function test_create_session_throws_duplicate_name_exception(): void
    {
        AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        $this->expectException(DuplicateSessionNameException::class);
        $this->expectExceptionMessage("An academic session with the name '2025/2026' already exists.");

        (new CreateSession)->execute([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);
    }

    public function test_create_session_throws_date_overlap_exception(): void
    {
        AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        $this->expectException(SessionDateRangeOverlapException::class);
        $this->expectExceptionMessage("The date range overlaps with an existing session ('2025/2026' — 2025-09-01 to 2026-06-30).");

        (new CreateSession)->execute([
            'name' => '2025/2026 Second',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => false,
        ]);
    }

    public function test_create_session_succeeds_with_no_overlap(): void
    {
        AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        $session = (new CreateSession)->execute([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_current' => false,
        ]);

        $this->assertDatabaseHas('academic_sessions', [
            'name' => '2026/2027',
            'id' => $session->id,
        ]);
    }

    public function test_update_session_throws_duplicate_name_exception(): void
    {
        AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        $session2 = AcademicSession::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_current' => false,
        ]);

        $this->expectException(DuplicateSessionNameException::class);

        (new UpdateSession)->execute($session2, ['name' => '2025/2026']);
    }

    public function test_update_session_throws_date_overlap_exception(): void
    {
        AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        $session2 = AcademicSession::create([
            'name' => '2026/2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_current' => false,
        ]);

        $this->expectException(SessionDateRangeOverlapException::class);

        (new UpdateSession)->execute($session2, [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
    }

    public function test_update_session_allows_same_name(): void
    {
        $session = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-06-30',
            'is_current' => false,
        ]);

        $updated = (new UpdateSession)->execute($session, ['name' => '2025/2026 Updated']);

        $this->assertDatabaseHas('academic_sessions', [
            'id' => $updated->id,
            'name' => '2025/2026 Updated',
        ]);
    }
}
