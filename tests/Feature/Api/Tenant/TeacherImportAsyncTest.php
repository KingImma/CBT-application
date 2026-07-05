<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\RoleType;
use App\Jobs\ProcessTeacherImportJob;
use App\Models\Tenant;
use App\Models\Tenant\ImportLog;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherImportAsyncTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::firstOrCreate(['name' => RoleType::SchoolAdmin->value, 'guard_name' => 'tenant']);
        Role::firstOrCreate(['name' => RoleType::Teacher->value, 'guard_name' => 'tenant']);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Async',
            'email' => 'admin-async@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole(RoleType::SchoolAdmin->value);

        Sanctum::actingAs($this->admin, ['*'], 'tenant');
    }

    protected function tearDown(): void
    {
        $this->tenant->database()->drop();
        parent::tearDown();
    }

    public function test_dry_run_still_returns_preview(): void
    {
        $csv = "first_name,last_name,email,staff_id,qualification\n"
            ."John,Doe,john@test.com,TCH/2026/001,B.Ed\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'true',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.dry_run', true);
    }

    public function test_async_import_creates_import_log_and_dispatches_job(): void
    {
        Queue::fake();

        $csv = "first_name,last_name,email,staff_id,qualification\n"
            ."John,Doe,john@test.com,TCH/2026/001,B.Ed\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(202);
        $response->assertJsonStructure([
            'data' => ['import_log_id', 'status'],
        ]);
        $response->assertJsonPath('data.status', 'pending');

        $importLogId = $response->json('data.import_log_id');
        $this->assertDatabaseHas('import_logs', [
            'id' => $importLogId,
            'type' => RoleType::Teacher->value,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessTeacherImportJob::class, function ($job) use ($importLogId) {
            return $job->importLogId === $importLogId
                && $job->tenantId === $this->tenant->id;
        });
    }

    public function test_async_import_processes_records_with_sync_queue(): void
    {
        $csv = "first_name,last_name,email,staff_id,qualification\n"
            ."John,Doe,john@test.com,TCH/2026/001,B.Ed\n"
            ."Jane,Smith,jane@test.com,,M.Sc\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(202);

        $importLogId = $response->json('data.import_log_id');

        $log = ImportLog::find($importLogId);
        $this->assertNotNull($log);
        $this->assertEquals('completed', $log->status);
        $this->assertEquals(2, $log->imported);

        $this->assertEquals(2, User::role(RoleType::Teacher->value)->count());
    }

    public function test_import_status_endpoint_returns_log(): void
    {
        $importLog = ImportLog::create([
            'type' => RoleType::Teacher->value,
            'filename' => 'test.csv',
            'status' => 'completed',
            'total_rows' => 5,
            'imported' => 4,
            'skipped' => 1,
            'updated' => 0,
            'created_by' => $this->admin->id,
            'completed_at' => now(),
        ]);

        $response = $this->getJson("/api/teachers/import/{$importLog->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.total_rows', 5);
        $response->assertJsonPath('data.imported', 4);
    }

    public function test_async_import_validates_missing_headers(): void
    {
        $csv = "first_name,email\n"
            ."John,john@test.com\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(422);
    }
}
