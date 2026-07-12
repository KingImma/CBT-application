<?php

namespace Tests\Feature\Api\Tenant;

use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\User;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserListQueryRegressionTest extends TestCase
{
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenantId = 'tenant-'.Str::uuid()->toString();

        $this->tenant = Tenant::factory()->create([
            'id' => $tenantId,
            'slug' => $tenantId,
            'handle' => $tenantId,
            'database' => 'tenant_'.str_replace('-', '_', $tenantId),
        ]);

        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        try {
            $this->tenant->delete();
        } catch (\Exception) {
            // Ignore cleanup failures.
        }

        parent::tearDown();
    }

    private function createUser(string $role, string $firstName, string $lastName, string $email, bool $isActive): User
    {
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role,
            'is_active' => $isActive,
        ]);

        $user->assignRole($role);

        return $user;
    }

    public function test_school_admin_can_list_students_with_status_and_search_filters(): void
    {
        $this->createUser(RoleType::Student->value, 'Ada', 'Lovelace', 'ada@example.com', true);
        $this->createUser(RoleType::Student->value, 'Grace', 'Hopper', 'grace@example.com', false);
        $admin = $this->createUser(RoleType::SchoolAdmin->value, 'School', 'Admin', 'admin@example.com', true);

        $this->actingAs($admin, 'tenant');

        $response = $this->getJson('/api/students?status=active&search=ada');

        $response->assertOk()
            ->assertJsonPath('message', 'Students retrieved successfully.')
            ->assertJsonCount(1, 'data');

        $this->assertSame('ada@example.com', $response->json('data.0.email'));
    }

    public function test_teacher_can_list_students_with_status_and_search_filters(): void
    {
        $this->createUser(RoleType::Student->value, 'Alan', 'Turing', 'alan@example.com', true);
        $this->createUser(RoleType::Student->value, 'Margaret', 'Hamilton', 'margaret@example.com', false);
        $teacher = $this->createUser(RoleType::Teacher->value, 'Teacher', 'User', 'teacher@example.com', true);

        $this->actingAs($teacher, 'tenant');

        $response = $this->getJson('/api/students?status=active&search=turing');

        $response->assertOk()
            ->assertJsonPath('message', 'Students retrieved successfully.')
            ->assertJsonCount(1, 'data');

        $this->assertSame('alan@example.com', $response->json('data.0.email'));
    }

    public function test_school_admin_can_list_teachers_with_status_and_search_filters(): void
    {
        $this->createUser(RoleType::Teacher->value, 'Katherine', 'Johnson', 'katherine@example.com', true);
        $this->createUser(RoleType::Teacher->value, 'Mary', 'Jackson', 'mary@example.com', false);
        $admin = $this->createUser(RoleType::SchoolAdmin->value, 'School', 'Admin', 'admin@example.com', true);

        $this->actingAs($admin, 'tenant');

        $response = $this->getJson('/api/teachers?status=active&search=johnson');

        $response->assertOk()
            ->assertJsonPath('message', 'Teachers retrieved successfully.')
            ->assertJsonCount(1, 'data');

        $this->assertSame('katherine@example.com', $response->json('data.0.email'));
    }

    public function test_revoked_teachers_appear_with_inactive_status(): void
    {
        $admin = $this->createUser(RoleType::SchoolAdmin->value, 'School', 'Admin', 'admin@example.com', true);
        $teacher = $this->createUser(RoleType::Teacher->value, 'Revoked', 'Teacher', 'revoked@example.com', true);

        // Simulate revoke: deactivate + soft delete
        $teacher->deactivate()->save();
        $teacher->delete();

        $this->actingAs($admin, 'tenant');

        $response = $this->getJson('/api/teachers?status=inactive');

        $response->assertOk()
            ->assertJsonPath('message', 'Teachers retrieved successfully.')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'revoked@example.com');
    }

    public function test_revoked_students_appear_with_inactive_status(): void
    {
        $admin = $this->createUser(RoleType::SchoolAdmin->value, 'School', 'Admin', 'admin@example.com', true);
        $student = $this->createUser(RoleType::Student->value, 'Revoked', 'Student', 'revoked@example.com', true);

        // Simulate revoke: deactivate + soft delete
        $student->deactivate()->save();
        $student->delete();

        $this->actingAs($admin, 'tenant');

        $response = $this->getJson('/api/students?status=inactive');

        $response->assertOk()
            ->assertJsonPath('message', 'Students retrieved successfully.')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'revoked@example.com');
    }

    public function test_revoked_users_appear_with_all_status(): void
    {
        $admin = $this->createUser(RoleType::SchoolAdmin->value, 'School', 'Admin', 'admin@example.com', true);
        $activeTeacher = $this->createUser(RoleType::Teacher->value, 'Active', 'Teacher', 'active@example.com', true);
        $revokedTeacher = $this->createUser(RoleType::Teacher->value, 'Revoked', 'Teacher', 'revoked@example.com', true);

        // Revoke one teacher
        $revokedTeacher->deactivate()->save();
        $revokedTeacher->delete();

        $this->actingAs($admin, 'tenant');

        $response = $this->getJson('/api/teachers?status=all');

        $response->assertOk()
            ->assertJsonPath('message', 'Teachers retrieved successfully.')
            ->assertJsonCount(2, 'data');
    }

    public function test_revoked_users_not_shown_with_active_status(): void
    {
        $admin = $this->createUser(RoleType::SchoolAdmin->value, 'School', 'Admin', 'admin@example.com', true);
        $activeTeacher = $this->createUser(RoleType::Teacher->value, 'Active', 'Teacher', 'active@example.com', true);
        $revokedTeacher = $this->createUser(RoleType::Teacher->value, 'Revoked', 'Teacher', 'revoked@example.com', true);

        // Revoke one teacher
        $revokedTeacher->deactivate()->save();
        $revokedTeacher->delete();

        $this->actingAs($admin, 'tenant');

        $response = $this->getJson('/api/teachers?status=active');

        $response->assertOk()
            ->assertJsonPath('message', 'Teachers retrieved successfully.')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'active@example.com');
    }

    public function test_revoked_teacher_can_be_restored(): void
    {
        $admin = $this->createUser(RoleType::SchoolAdmin->value, 'School', 'Admin', 'admin@example.com', true);
        $teacher = $this->createUser(RoleType::Teacher->value, 'Revoked', 'Teacher', 'revoked@example.com', true);

        // Revoke the teacher
        $teacher->deactivate()->save();
        $teacher->delete();

        $this->actingAs($admin, 'tenant');

        // Verify teacher is not in active list
        $response = $this->getJson('/api/teachers?status=active');
        $response->assertOk()->assertJsonCount(0, 'data');

        // Restore the teacher
        $response = $this->postJson("/api/teachers/{$teacher->id}/restore");
        $response->assertOk()
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'has been restored'));

        // Verify teacher is back in active list
        $response = $this->getJson('/api/teachers?status=active');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'revoked@example.com');
    }

    public function test_revoked_student_can_be_restored(): void
    {
        $admin = $this->createUser(RoleType::SchoolAdmin->value, 'School', 'Admin', 'admin@example.com', true);
        $student = $this->createUser(RoleType::Student->value, 'Revoked', 'Student', 'revoked@example.com', true);

        // Revoke the student
        $student->deactivate()->save();
        $student->delete();

        $this->actingAs($admin, 'tenant');

        // Verify student is not in active list
        $response = $this->getJson('/api/students?status=active');
        $response->assertOk()->assertJsonCount(0, 'data');

        // Restore the student
        $response = $this->postJson("/api/students/{$student->id}/restore");
        $response->assertOk()
            ->assertJsonPath('message', fn ($msg) => str_contains($msg, 'has been restored'));

        // Verify student is back in active list
        $response = $this->getJson('/api/students?status=active');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'revoked@example.com');
    }
}
