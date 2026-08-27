<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Domains\Import\Data\ImportResult;
use App\Domains\Import\Jobs\ImportStudentsJob;
use App\Enums\RoleType;
use App\Events\ActivityFeedEvent;
use App\Models\Tenant;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected ClassLevel $jss1;

    protected ClassLevel $jss2;

    protected ClassArm $armA;

    protected ClassArm $armB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetCentralTables();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::firstOrCreate(['name' => RoleType::SchoolAdmin->value, 'guard_name' => 'tenant']);
        Role::firstOrCreate(['name' => RoleType::Student->value, 'guard_name' => 'tenant']);
        Role::firstOrCreate(['name' => RoleType::Teacher->value, 'guard_name' => 'tenant']);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Import',
            'email' => 'admin-import@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole(RoleType::SchoolAdmin->value);

        $this->jss1 = ClassLevel::create(['name' => 'JSS 1', 'slug' => 'jss-1']);
        $this->jss2 = ClassLevel::create(['name' => 'JSS 2', 'slug' => 'jss-2']);
        $this->armA = ClassArm::create(['name' => 'A', 'class_level_id' => $this->jss1->id]);
        $this->armB = ClassArm::create(['name' => 'B', 'class_level_id' => $this->jss2->id]);
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

        $this->resetCentralTables();

        parent::tearDown();
    }

    private function resetCentralTables(): void
    {
        $central = config('tenancy.database.central_connection');

        foreach (['import_jobs', 'subscription_plans', 'tenants'] as $table) {
            DB::connection($central)->table($table)->delete();
        }
    }

    private function runStudentJob(string $csv, array $validated = [], string $status = 'pending'): string
    {
        $importJobId = Str::uuid()->toString();
        $central = config('tenancy.database.central_connection');

        DB::connection($central)->table('import_jobs')->insert([
            'id' => $importJobId,
            'tenant_id' => $this->tenant->id,
            'type' => 'student',
            'status' => $status,
            'file_contents' => $csv,
            'meta' => json_encode($validated),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        (new ImportStudentsJob($importJobId))->handle();

        // handle() calls tenancy()->end() in its finally block, which reverts
        // the default connection to central. Re-initialize so subsequent
        // assertions query the tenant DB.
        tenancy()->initialize($this->tenant);

        return $importJobId;
    }

    public function test_dry_run_returns_preview(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,A\n"
            ."Bob,Smith,bob@test.com,STU/2026/0001,JSS 2,B\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'true',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.dry_run', true);
        $response->assertJsonPath('data.total_rows', 2);
    }

    public function test_import_handles_missing_headers(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,email,class_level\n"
            ."Alice,alice@test.com,JSS 1\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'true',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['missing_headers']]);
    }

    public function test_import_rejects_unknown_class_level_on_dry_run(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,Unknown Level,A\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'true',
        ]);

        $response->assertStatus(422);
    }

    public function test_real_import_is_queued_and_returns_202(): void
    {
        Queue::fake();
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,A\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(202);

        Queue::assertPushed(ImportStudentsJob::class, function (ImportStudentsJob $job) {
            $ref = new \ReflectionProperty($job, 'importJobId');
            $ref->setAccessible(true);
            $importJobId = $ref->getValue($job);

            return DB::connection(config('tenancy.database.central_connection'))
                ->table('import_jobs')
                ->where('id', $importJobId)
                ->where('tenant_id', $this->tenant->id)
                ->where('status', 'pending')
                ->exists();
        });

        // Work happens in the queue, not the request.
        $this->assertEquals(0, User::role(RoleType::Student->value)->count());
    }

    public function test_job_creates_students(): void
    {
        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,A\n"
            ."Bob,Smith,bob@test.com,STU/2026/0001,JSS 2,B\n";

        $this->runStudentJob($csv);

        $this->assertEquals(2, User::role(RoleType::Student->value)->count());
    }

    public function test_job_resolves_class_arm_case_insensitively(): void
    {
        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,a\n";

        $this->runStudentJob($csv);

        $alice = User::where('email', 'alice@test.com')->firstOrFail();
        $this->assertEquals($this->jss1->id, $alice->studentProfile->class_level_id);
        $this->assertEquals($this->armA->id, $alice->studentProfile->class_arm_id);
    }

    public function test_dry_run_rejects_unknown_class_arm(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,Z\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'true',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.errors.class_arm.0', "Class arm 'Z' not found for class level 'JSS 1'.");
    }

    public function test_job_resolves_short_arm_token_to_long_arm_name(): void
    {
        $this->armA->update(['name' => 'Junior Secondary A']);

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,A\n";

        $this->runStudentJob($csv);

        $alice = User::where('email', 'alice@test.com')->firstOrFail();
        $this->assertEquals($this->jss1->id, $alice->studentProfile->class_level_id);
        $this->assertEquals($this->armA->id, $alice->studentProfile->class_arm_id);
    }

    public function test_dry_run_rejects_ambiguous_class_arm(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        ClassArm::create(['name' => 'Senior Secondary A', 'class_level_id' => $this->jss1->id]);

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,A\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'true',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.0.errors.class_arm.0', "Class arm 'A' is ambiguous for class level 'JSS 1' (matches: A, Senior Secondary A). Use the full name.");
    }

    public function test_job_marks_failed_and_broadcasts_failure_when_arm_unresolvable(): void
    {
        Event::fake();

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,Z\n";

        $importJobId = $this->runStudentJob($csv);

        $central = config('tenancy.database.central_connection');
        $row = DB::connection($central)->table('import_jobs')->where('id', $importJobId)->first();

        $this->assertSame('failed', $row->status);
        $this->assertNotNull($row->retain_until);

        $this->assertEquals(0, User::role(RoleType::Student->value)->count());

        Event::assertDispatched(ActivityFeedEvent::class, function (ActivityFeedEvent $event) {
            return $event->action === 'student_import_failed'
                && ! empty($event->meta['errors']);
        });
    }

    public function test_job_skips_existing_email(): void
    {
        $existing = User::create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'duplicate@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $existing->assignRole(RoleType::Student->value);
        $existing->studentProfile()->create([
            'class_level_id' => $this->jss1->id,
            'class_arm_id' => $this->armA->id,
            'admission_number' => 'STU/2026/9999',
        ]);

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,duplicate@test.com,,JSS 1,A\n";

        $this->runStudentJob($csv);

        $this->assertEquals(1, User::role(RoleType::Student->value)->count());
    }

    public function test_job_overwrite_updates_existing(): void
    {
        $existing = User::create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'update@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $existing->assignRole(RoleType::Student->value);
        $existing->studentProfile()->create([
            'class_level_id' => $this->jss2->id,
            'class_arm_id' => null,
            'admission_number' => 'STU/2026/UPD',
        ]);

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."New,Name,update@test.com,STU/2026/UPD,JSS 1,A\n";

        $this->runStudentJob($csv, ['overwrite_existing' => 'update']);

        $existing->refresh();
        $this->assertEquals('New', $existing->first_name);
        $this->assertEquals($this->jss1->id, $existing->studentProfile->class_level_id);
        $this->assertEquals($this->armA->id, $existing->studentProfile->class_arm_id);
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

        $job = new ImportStudentsJob(Str::uuid()->toString());
        $job->notifyComplete($result, 'abc');

        Event::assertDispatched(ActivityFeedEvent::class, function (ActivityFeedEvent $event) {
            return $event->action === 'student_import_completed'
                && $event->channelType === 'school_admin'
                && ($event->meta['imported'] ?? 0) === 1;
        });
    }
}
