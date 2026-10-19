<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentGuardianTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected ClassLevel $classLevel;

    protected ClassArm $classArm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetCentralTables();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::firstOrCreate(['name' => RoleType::SchoolAdmin->value, 'guard_name' => 'tenant']);
        Role::firstOrCreate(['name' => RoleType::Student->value, 'guard_name' => 'tenant']);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'Guardian',
            'email' => 'admin-guardian@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole(RoleType::SchoolAdmin->value);

        $this->classLevel = ClassLevel::create(['name' => 'JSS 1', 'slug' => 'jss-1']);
        $this->classArm = ClassArm::create(['name' => 'A', 'class_level_id' => $this->classLevel->id]);

        Sanctum::actingAs($this->admin, ['*'], 'tenant');
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

    public function test_guardian_details_are_persisted_when_creating_a_student(): void
    {
        $response = $this->postJson('/api/students', [
            'first_name' => 'Grace',
            'last_name' => 'Sunday',
            'email' => 'grace@test.com',
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'guardian_name' => 'Mrs. Blessing Sunday',
            'guardian_phone' => '+2348012345678',
            'guardian_email' => 'guardian@test.com',
        ]);

        $response->assertSuccessful();

        $profile = User::where('email', 'grace@test.com')->firstOrFail()->studentProfile;

        $this->assertSame('Mrs. Blessing Sunday', $profile->guardian_name);
        $this->assertSame('+2348012345678', $profile->guardian_phone);
        $this->assertSame('guardian@test.com', $profile->guardian_email);
    }

    public function test_guardian_details_are_persisted_when_updating_a_student(): void
    {
        $student = User::create([
            'first_name' => 'Grace',
            'last_name' => 'Sunday',
            'email' => 'grace@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $student->assignRole(RoleType::Student->value);
        $student->studentProfile()->create([
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'admission_number' => 'STU/2026/0001',
        ]);

        $response = $this->patchJson("/api/students/{$student->id}", [
            'guardian_name' => 'Mr. Peter Sunday',
            'guardian_phone' => '+2348098765432',
            'guardian_email' => 'peter@test.com',
        ]);

        $response->assertSuccessful();

        $student->refresh()->load('studentProfile');

        $this->assertSame('Mr. Peter Sunday', $student->studentProfile->guardian_name);
        $this->assertSame('+2348098765432', $student->studentProfile->guardian_phone);
        $this->assertSame('peter@test.com', $student->studentProfile->guardian_email);
    }
}
