<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OnboardingHandleCheckTest extends TestCase
{
    use RefreshDatabase {
        beginDatabaseTransaction as baseBeginDatabaseTransaction;
    }

    protected function beginDatabaseTransaction(): void
    {
        // No-op: Tenant factory creates databases, which cannot run
        // inside a transaction block. Data isolation is handled
        // manually in setUp.
    }

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->delete();
    }

    protected function tearDown(): void
    {
        DB::table('tenants')->delete();

        parent::tearDown();
    }

    #[Test]
    public function returns_available_for_unused_handle(): void
    {
        $response = $this->getJson('/api/onboarding/check-handle?handle=new-school');

        $response->assertOk();
        $response->assertJsonPath('data.available', true);
    }

    #[Test]
    public function returns_unavailable_for_taken_handle(): void
    {
        Tenant::withoutEvents(fn () => Tenant::factory()->create(['handle' => 'taken-handle']));

        $response = $this->getJson('/api/onboarding/check-handle?handle=taken-handle');

        $response->assertOk();
        $response->assertJsonPath('data.available', false);
    }

    #[Test]
    public function accepts_handles_up_to_63_characters(): void
    {
        $handle = str_repeat('a', 63);
        $response = $this->getJson("/api/onboarding/check-handle?handle={$handle}");

        $response->assertOk();
    }

    #[Test]
    public function rejects_handles_longer_than_63_characters(): void
    {
        $response = $this->getJson('/api/onboarding/check-handle?handle='.str_repeat('a', 64));

        $response->assertStatus(422);
    }

    #[Test]
    public function rejects_request_without_handle_parameter(): void
    {
        $response = $this->getJson('/api/onboarding/check-handle');

        $response->assertStatus(422);
    }

    #[Test]
    public function does_not_require_authentication(): void
    {
        $response = $this->getJson('/api/onboarding/check-handle?handle=new-school');

        $response->assertOk();
    }
}
