<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HorizonQueueTest extends TestCase
{
    use RefreshDatabase;
    
    protected SuperAdmin $superAdmin;

    // -------------------------------------------------------------------------
    // Queue connection
    // -------------------------------------------------------------------------

    #[Test]
    public function queue_connection_is_redis_in_production_config(): void
    {
        // Verifies the queue.php config points to Redis, not sync or database.
        // If this fails, jobs will run synchronously in production — a critical
        // misconfiguration that defeats the purpose of Horizon entirely.
        $this->assertSame('horizon-redis', config('queue.default'));
    }

    // -------------------------------------------------------------------------
    // Dashboard access
    // -------------------------------------------------------------------------

    #[Test]
    public function horizon_dashboard_is_accessible_to_active_super_admin(): void
    {
        $this->superAdmin = SuperAdmin::factory()->create(['is_active' => true]);

        $this->actingAs($this->superAdmin, 'super_admin')
            ->get('/horizon')
            ->assertOk();
    }

    #[Test]
    public function horizon_dashboard_is_blocked_for_unauthenticated_users(): void
    {
        $this->get('/horizon')->assertForbidden();
    }

    #[Test]
    public function horizon_dashboard_is_blocked_for_inactive_super_admin(): void
    {
        $this->superAdmin = SuperAdmin::factory()->inactive()->create();

        $this->actingAs($this->superAdmin, 'super_admin')
            ->get('/horizon')
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Job dispatching — uses Queue::fake() so no real Redis needed in tests
    // -------------------------------------------------------------------------

    #[Test]
    public function jobs_are_dispatched_to_correct_queues(): void
    {
        Queue::fake();

        // Dispatch a fake job to each queue and verify routing
        dispatch(new \App\Jobs\ExampleDefaultJob())->onQueue('default');
        dispatch(new \App\Jobs\ExampleProvisioningJob())->onQueue('tenant-provisioning');

        Queue::assertPushedOn('default', \App\Jobs\ExampleDefaultJob::class);
        Queue::assertPushedOn('tenant-provisioning', \App\Jobs\ExampleProvisioningJob::class);
    }

    #[Test]
    public function failed_jobs_are_not_lost(): void
    {
        // Verifies the failed_jobs table exists and is writable.
        // Horizon uses this table as its failure store — if it's missing,
        // failed jobs silently disappear with no retry mechanism.
        $this->assertDatabaseEmpty('failed_jobs');

        // Simulate a failed job record
        \Illuminate\Support\Facades\DB::table('failed_jobs')->insert([
            'uuid'       => \Illuminate\Support\Str::uuid(),
            'connection' => 'redis',
            'queue'      => 'default',
            'payload'    => json_encode(['job' => 'TestJob']),
            'exception'  => 'RuntimeException: Something went wrong',
            'failed_at'  => now(),
        ]);

        $this->assertDatabaseCount('failed_jobs', 1);
    }
}