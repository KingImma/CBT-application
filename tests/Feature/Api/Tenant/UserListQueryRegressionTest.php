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
}
