<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Data\Results\ImportResult;
use App\Enums\RoleType;
use App\Events\ActivityFeedEvent;
use App\Jobs\ImportTeachersJob;
use App\Models\Tenant;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherImportTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected string $central;

    protected function setUp(): void
    {
        parent::setUp();

        $this->central = config('tenancy.database.central_connection');

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::firstOrCreate(['name' => RoleType::SchoolAdmin->value, 'guard_name' => 'tenant']);
        Role::firstOrCreate(['name' => RoleType::Teacher->value, 'guard_name' => 'tenant']);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Import',
            'email' => 'admin-teacher-import@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole(RoleType::SchoolAdmin->value);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        $tenantDbName = $this->tenant->database;
        try {
            DB::connection('pgsql')->statement(
                'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()',
                [$tenantDbName]
            );
        } catch (\Throwable) {
            // Connection may not exist yet
        }

        DB::purge('pgsql');
        $this->tenant->delete();
        parent::tearDown();
    }

    /**
     * Inserts an import_jobs row on the central connection and runs the
     * job's handle() synchronously, mirroring what the queue worker does.
     */
    private function runTeacherJob(string $csv, array $validated = [], string $status = 'pending'): string
    {
        $importJobId = Str::uuid()->toString();

        DB::connection($this->central)->table('import_jobs')->insert([
            'id' => $importJobId,
            'tenant_id' => $this->tenant->id,
            'type' => 'teacher',
            'status' => $status,
            'file_contents' => $csv,
            'meta' => json_encode($validated),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new ImportTeachersJob($importJobId))->handle();

        // handle() calls tenancy()->end() in its finally block, which reverts
        // the default connection to central. Re-initialize so subsequent
        // assertions query the tenant DB.
        tenancy()->initialize($this->tenant);

        return $importJobId;
    }

    public function test_dry_run_returns_preview(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,qualification,staff_id\n"
            ."Alice,Johnson,alice@test.com,BSc,TCH/2026/0001\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'true',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.dry_run', true);
        $response->assertJsonPath('data.total_rows', 1);
    }

    public function test_real_import_is_queued_and_returns_202(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,qualification,staff_id\n"
            ."Alice,Johnson,alice@test.com,BSc,TCH/2026/0001\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(ImportTeachersJob::class, function (ImportTeachersJob $job) {
            $ref = new \ReflectionProperty($job, 'importJobId');
            $ref->setAccessible(true);
            $importJobId = $ref->getValue($job);

            return DB::connection($this->central)
                ->table('import_jobs')
                ->where('id', $importJobId)
                ->where('tenant_id', $this->tenant->id)
                ->where('status', 'pending')
                ->exists();
        });

        // Row must be created durably before dispatch, regardless of queue outcome.
        $this->assertDatabaseHas('import_jobs', [
            'tenant_id' => $this->tenant->id,
            'type' => 'teacher',
            'status' => 'pending',
        ], $this->central);

        // Since the queue is faked, the job never ran — no teachers created yet.
        $this->assertEquals(0, User::role(RoleType::Teacher->value)->count());
    }

    public function test_job_creates_teachers(): void
    {
        $csv = "first_name,last_name,email,qualification,staff_id\n"
            ."Alice,Johnson,alice@test.com,BSc,TCH/2026/0001\n"
            ."Bob,Smith,bob@test.com,MSc,TCH/2026/0002\n";

        $importJobId = $this->runTeacherJob($csv);

        $this->assertEquals(2, User::role(RoleType::Teacher->value)->count());

        // Row is deleted on successful completion.
        $this->assertDatabaseMissing('import_jobs', [
            'id' => $importJobId,
        ], $this->central);
    }

    public function test_job_overwrite_updates_existing(): void
    {
        $existing = User::create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'update@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $existing->assignRole(RoleType::Teacher->value);
        $existing->teacherProfile()->create(['staff_id' => 'TCH/2026/UPD']);

        $csv = "first_name,last_name,email,qualification,staff_id\n"
            ."New,Name,update@test.com,PhD,TCH/2026/UPD\n";

        $this->runTeacherJob($csv, ['overwrite_existing' => 'update']);

        $existing->refresh();
        $this->assertEquals('New', $existing->first_name);
        $this->assertEquals('PhD', $existing->teacherProfile->qualification);
    }

    public function test_job_skips_already_completed_row(): void
    {
        $csv = "first_name,last_name,email,qualification,staff_id\n"
            ."Alice,Johnson,alice@test.com,BSc,TCH/2026/0001\n";

        $importJobId = $this->runTeacherJob($csv, [], status: 'completed');

        // Nothing should have been imported — job bails out on 'completed' status.
        $this->assertEquals(0, User::role(RoleType::Teacher->value)->count());

        // Row remains untouched (not deleted, since handle() returns early).
        $this->assertDatabaseHas('import_jobs', [
            'id' => $importJobId,
            'status' => 'completed',
        ], $this->central);
    }

    public function test_job_marks_row_failed_on_permanent_failure(): void
    {
        $importJobId = Str::uuid()->toString();

        DB::connection($this->central)->table('import_jobs')->insert([
            'id' => $importJobId,
            'tenant_id' => $this->tenant->id,
            'type' => 'teacher',
            'status' => 'processing',
            'file_contents' => '',
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new ImportTeachersJob($importJobId))->failed(new \RuntimeException('boom'));

        $this->assertDatabaseHas('import_jobs', [
            'id' => $importJobId,
            'status' => 'failed',
        ], $this->central);
    }

    public function test_job_fires_completion_event(): void
    {
        Event::fake();

        $result = new ImportResult(
            success: true,
            totalRows: 1,
            imported: 1,
            skipped: 0,
            updated: 0,
        );

        $job = new ImportTeachersJob(Str::uuid()->toString());
        $job->notifyComplete($result, $this->tenant->id);

        Event::assertDispatched(ActivityFeedEvent::class, function (ActivityFeedEvent $event) {
            return $event->action === 'teacher_import_completed'
                && $event->channelType === 'school_admin'
                && $event->channelId === $this->tenant->id
                && ($event->meta['imported'] ?? 0) === 1;
        });
    }
}
