<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

abstract class BaseTenantTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

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
            'last_name' => 'Test',
            'email' => 'admin-'.$this->tenant->id.'@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole(RoleType::SchoolAdmin->value);

        Sanctum::actingAs($this->admin, ['*'], 'tenant');
    }

    protected function tearDown(): void
    {
        if (isset($this->tenant)) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    protected function createTeacher(array $overrides = []): User
    {
        $teacher = User::create(array_merge([
            'first_name' => 'Teacher',
            'last_name' => 'Test',
            'email' => 'teacher-'.$this->tenant->id.'@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ], $overrides));
        $teacher->assignRole(RoleType::Teacher->value);

        $teacher->teacherProfile()->create([
            'staff_id' => $overrides['staff_id'] ?? 'TCH/'.date('Y').'/001',
        ]);

        return $teacher;
    }

    protected function createStudent(array $overrides = []): User
    {
        $classLevel = ClassLevel::factory()->create();

        $student = User::create(array_merge([
            'first_name' => 'Student',
            'last_name' => 'Test',
            'email' => 'student-'.$this->tenant->id.'@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ], $overrides));
        $student->assignRole(RoleType::Student->value);

        $student->studentProfile()->create([
            'class_level_id' => $classLevel->id,
            'admission_number' => 'STU/'.date('Y').'/0001',
        ]);

        return $student;
    }
}
