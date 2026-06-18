<?php

namespace Tests\Feature\Api\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\Subject;
use App\Models\Tenant\User;
use Tests\TestCase;

class SubjectUniqueValidationTest extends TestCase
{
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);
    }

    protected function tearDown(): void
    {
        try {
            $this->tenant->database()->manager()->deleteDatabase($this->tenant);
        } catch (\Exception) {
            // Ignore cleanup failures.
        }
        parent::tearDown();
    }

    private function createAdmin(): User
    {
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'school_admin',
            'is_active' => true,
        ]);
        $admin->assignRole('school_admin');

        return $admin;
    }

    public function test_case_variant_name_is_treated_as_duplicate(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/subjects', ['name' => 'Mathematics'])->assertStatus(201);
        $this->postJson('/api/subjects', ['name' => 'mathematics'])->assertStatus(422);
    }

    public function test_whitespace_variant_name_is_treated_as_duplicate(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/subjects', ['name' => 'Mathematics'])->assertStatus(201);
        $this->postJson('/api/subjects', ['name' => '  Mathematics  '])->assertStatus(422);
    }

    public function test_update_with_same_name_passes(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/subjects', ['name' => 'Mathematics'])->assertStatus(201);
        $subject = Subject::firstWhere('name', 'MATHEMATICS');

        $this->patchJson("/api/subjects/{$subject->id}", ['name' => 'Mathematics'])->assertStatus(200);
    }

    public function test_update_to_conflicting_name_fails(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/subjects', ['name' => 'Mathematics'])->assertStatus(201);
        $this->postJson('/api/subjects', ['name' => 'Physics'])->assertStatus(201);

        $subject = Subject::firstWhere('name', 'MATHEMATICS');

        $this->patchJson("/api/subjects/{$subject->id}", ['name' => 'Physics'])->assertStatus(422);
    }

    public function test_subject_can_be_deactivated(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/subjects', ['name' => 'Mathematics'])->assertStatus(201);
        $subject = Subject::firstWhere('name', 'MATHEMATICS');

        $this->deleteJson("/api/subjects/{$subject->id}")->assertStatus(200);

        $this->assertDatabaseHas('subjects', [
            'id' => $subject->id,
            'is_active' => false,
        ]);
    }
}
