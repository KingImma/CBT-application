<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\RoleType;
use App\Jobs\ProcessStudentImportJob;
use App\Models\Tenant;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\ImportLog;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentImportAsyncTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected ClassLevel $jss1;

    protected ClassLevel $ss2;

    protected ClassArm $armA;

    protected ClassArm $armB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::firstOrCreate(['name' => RoleType::SchoolAdmin->value, 'guard_name' => 'tenant']);
        Role::firstOrCreate(['name' => RoleType::Student->value, 'guard_name' => 'tenant']);
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

        $this->jss1 = ClassLevel::create(['name' => 'JSS 1']);
        $this->ss2 = ClassLevel::create(['name' => 'SS 2']);
        $this->armA = ClassArm::create(['name' => 'A', 'class_level_id' => $this->jss1->id]);
        $this->armB = ClassArm::create(['name' => 'B', 'class_level_id' => $this->ss2->id]);

        Sanctum::actingAs($this->admin, ['*'], 'tenant');
    }

    protected function tearDown(): void
    {
        $this->tenant->database()->drop();
        parent::tearDown();
    }

    public function test_async_import_dispatches_job_and_creates_import_log(): void
    {
        Queue::fake();

        $csv = "first_name,last_name,email,class_level,class_arm\n"
            ."Bob,Brown,bob@test.com,JSS 1,A\n"
            ."Alice,Green,alice@test.com,SS 2,B\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.status', 'pending');

        $importLogId = $response->json('data.import_log_id');

        $this->assertDatabaseHas('import_logs', [
            'id' => $importLogId,
            'type' => RoleType::Student->value,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessStudentImportJob::class, function ($job) use ($importLogId) {
            return $job->importLogId === $importLogId
                && $job->tenantId === $this->tenant->id;
        });
    }

    public function test_async_import_creates_students_with_sync_queue(): void
    {
        $csv = "first_name,last_name,email,class_level,class_arm\n"
            ."Bob,Brown,bob@test.com,JSS 1,A\n"
            ."Alice,Green,alice@test.com,SS 2,B\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(202);

        $importLogId = $response->json('data.import_log_id');

        $log = ImportLog::find($importLogId);
        $this->assertNotNull($log);
        $this->assertEquals('completed', $log->status);
        $this->assertEquals(2, $log->imported);

        $this->assertEquals(2, User::role(RoleType::Student->value)->count());
    }

    public function test_import_status_endpoint_returns_log(): void
    {
        $importLog = ImportLog::create([
            'type' => RoleType::Student->value,
            'filename' => 'test.csv',
            'status' => 'failed',
            'total_rows' => 3,
            'imported' => 0,
            'errors' => [['row' => 1, 'message' => 'Invalid email']],
            'created_by' => $this->admin->id,
            'completed_at' => now(),
        ]);

        $response = $this->getJson("/api/students/import/{$importLog->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'failed');
        $response->assertJsonPath('data.errors', [['row' => 1, 'message' => 'Invalid email']]);
    }

    public function test_async_import_validates_missing_headers(): void
    {
        $csv = "first_name,email\n"
            ."Bob,bob@test.com\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(422);
    }
}
