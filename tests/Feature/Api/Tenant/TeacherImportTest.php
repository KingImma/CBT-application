<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherImportTest extends TestCase
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
            'last_name' => 'Import',
            'email' => 'admin-import@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole(RoleType::SchoolAdmin->value);
    }

    protected function tearDown(): void
    {
        $this->tenant->database()->drop();
        parent::tearDown();
    }

    public function test_dry_run_returns_preview(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,staff_id,qualification\n"
            ."John,Doe,john@test.com,TCH/2026/001,B.Ed\n"
            ."Jane,Smith,jane@test.com,,M.Sc\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'true',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.dry_run', true);
        $response->assertJsonPath('data.total_rows', 2);
    }

    public function test_import_creates_teachers(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,staff_id,qualification\n"
            ."John,Doe,john@test.com,TCH/2026/001,B.Ed\n"
            ."Jane,Smith,jane@test.com,,M.Sc\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.imported', 2);

        $this->assertEquals(2, User::role(RoleType::Teacher->value)->count());
    }

    public function test_import_detects_duplicate_by_email(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $existing = User::create([
            'first_name' => 'Existing',
            'last_name' => 'Teacher',
            'email' => 'dup@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $existing->assignRole(RoleType::Teacher->value);
        $existing->teacherProfile()->create([
            'staff_id' => 'TCH/2026/999',
        ]);

        $csv = "first_name,last_name,email,staff_id\n"
            ."New,Teacher,dup@test.com,TCH/2026/001\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(422);
    }

    public function test_overwrite_existing_update_modifies_existing_teacher(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $existing = User::create([
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'update@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $existing->assignRole(RoleType::Teacher->value);
        $existing->teacherProfile()->create([
            'staff_id' => 'TCH/2026/UPD',
            'qualification' => 'B.Ed',
        ]);

        $csv = "first_name,last_name,email,staff_id,qualification\n"
            ."New,Name,update@test.com,TCH/2026/UPD,M.Sc\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'false',
            'overwrite_existing' => 'update',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.updated', 1);

        $existing->refresh();
        $this->assertEquals('New', $existing->first_name);
        $this->assertEquals('Name', $existing->last_name);
        $this->assertEquals('M.Sc', $existing->teacherProfile->qualification);
    }

    public function test_import_handles_missing_headers(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,email\n"
            ."John,john@test.com\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'true',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['missing_headers']);
    }

    public function test_import_requires_email(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,staff_id\n"
            ."John,Doe,TCH/2026/001\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $response = $this->postJson('/api/teachers/import', [
            'file' => $file,
            'dry_run' => 'false',
        ]);

        $response->assertStatus(422);
    }

    public function test_import_is_idempotent_with_skip(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $csv = "first_name,last_name,email,staff_id\n"
            ."John,Doe,john@test.com,TCH/2026/001\n";

        $file = UploadedFile::fake()->createWithContent('teachers.csv', $csv);

        $this->postJson('/api/teachers/import', ['file' => $file, 'dry_run' => 'false'])->assertStatus(201);
        $this->postJson('/api/teachers/import', ['file' => $file, 'dry_run' => 'false'])->assertStatus(422);

        $this->assertEquals(1, User::role(RoleType::Teacher->value)->count());
    }
}
