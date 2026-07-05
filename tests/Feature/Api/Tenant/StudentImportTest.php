<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected ClassLevel $jss1;

    protected ClassLevel $jss2;

    protected ClassLevel $ss1;

    protected ClassLevel $ss2;

    protected ClassLevel $ss3;

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
            'last_name' => 'Import',
            'email' => 'admin-import@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole(RoleType::SchoolAdmin->value);

        $this->jss1 = ClassLevel::create(['name' => 'JSS 1']);
        $this->jss2 = ClassLevel::create(['name' => 'JSS 2']);
        $this->ss1 = ClassLevel::create(['name' => 'SS 1']);
        $this->ss2 = ClassLevel::create(['name' => 'SS 2']);
        $this->ss3 = ClassLevel::create(['name' => 'SS 3']);

        $this->armA = ClassArm::create(['name' => 'A', 'class_level_id' => $this->jss1->id]);
        $this->armB = ClassArm::create(['name' => 'B', 'class_level_id' => $this->ss2->id]);
        ClassArm::create(['name' => 'A', 'class_level_id' => $this->ss1->id]);
        ClassArm::create(['name' => 'B', 'class_level_id' => $this->ss3->id]);
    }

    protected function tearDown(): void
    {
        $this->tenant->database()->drop();
        parent::tearDown();
    }

    public function test_dry_run_returns_preview(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,A\n"
            ."Bob,Smith,bob@test.com,STU/2026/0001,SS 2,B\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'true',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.dry_run', true);
        $response->assertJsonPath('data.total_rows', 2);
    }

    public function test_import_creates_students(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,A\n"
            ."Bob,Smith,bob@test.com,STU/2026/0001,SS 2,B\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported', 2);

        $this->assertEquals(2, User::role(RoleType::Student->value)->count());
    }

    public function test_import_detects_duplicate_by_email(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

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

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Existing records will be skipped. Set overwrite_existing=update to overwrite.');
    }

    public function test_import_detects_duplicate_by_admission_number(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $existing = User::create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'existing@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $existing->assignRole(RoleType::Student->value);
        $existing->studentProfile()->create([
            'class_level_id' => $this->jss1->id,
            'class_arm_id' => $this->armA->id,
            'admission_number' => 'STU/2026/DUPE',
        ]);

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,new@test.com,STU/2026/DUPE,JSS 1,A\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(422);
    }

    public function test_overwrite_existing_update_modifies_existing_student(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

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

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'false',
            'overwrite_existing' => 'update',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.updated', 1);

        $existing->refresh();
        $this->assertEquals('New', $existing->first_name);
        $this->assertEquals('Name', $existing->last_name);
        $this->assertEquals($this->jss1->id, $existing->studentProfile->class_level_id);
        $this->assertEquals($this->armA->id, $existing->studentProfile->class_arm_id);
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
        $response->assertJsonStructure(['missing_headers']);
    }

    public function test_import_rejects_unknown_class_level(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,Unknown Level,A\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(422);
    }

    public function test_import_with_fuzzy_headers(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "First Name,Last Name,Email,Admission #,Grade,Section\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,A\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported', 1);
    }

    public function test_empty_csv_returns_no_rows(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->postJson('/api/students/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported', 0);
    }

    public function test_import_is_idempotent_with_skip(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,admission_number,class_level,class_arm\n"
            ."Alice,Johnson,alice@test.com,,JSS 1,A\n";

        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $this->postJson('/api/students/import', ['file' => $file, 'dry_run' => 'false'])->assertStatus(201);

        $this->postJson('/api/students/import', ['file' => $file, 'dry_run' => 'false'])->assertStatus(422);

        $this->assertEquals(1, User::role(RoleType::Student->value)->count());
    }
}
