<?php

namespace Tests\Feature\Api\Tenant;

use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Tests\TestCase;

class ClassLevelUniqueValidationTest extends TestCase
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

    // --- Class Level tests ---

    public function test_case_variant_name_is_treated_as_duplicate(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'Jss 1'])->assertStatus(201);
        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(422);
    }

    public function test_whitespace_variant_name_is_treated_as_duplicate(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $this->postJson('/api/class-levels', ['name' => '  JSS 1  '])->assertStatus(422);
    }

    public function test_soft_deleted_class_level_can_be_recreated(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $level = ClassLevel::firstWhere('name', 'JSS 1');

        $level->delete(); // soft-delete

        $this->assertSoftDeleted($level);

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
    }

    public function test_update_with_same_name_passes(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $level = ClassLevel::firstWhere('name', 'JSS 1');

        $this->patchJson("/api/class-levels/{$level->id}", ['name' => 'JSS 1'])->assertStatus(200);
    }

    public function test_update_to_conflicting_name_fails(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $this->postJson('/api/class-levels', ['name' => 'JSS 2'])->assertStatus(201);

        $level = ClassLevel::firstWhere('name', 'JSS 1');

        $this->patchJson("/api/class-levels/{$level->id}", ['name' => 'JSS 2'])->assertStatus(422);
    }

    public function test_delete_with_students_soft_deletes(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $level = ClassLevel::firstWhere('name', 'JSS 1');

        $student = User::create([
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => 'student@test.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'is_active' => true,
        ]);
        StudentProfile::create([
            'user_id' => $student->id,
            'class_level_id' => $level->id,
            'admission_number' => 'STU/2026/0001',
        ]);

        $this->deleteJson("/api/class-levels/{$level->id}")->assertStatus(200);

        $this->assertSoftDeleted($level->fresh());
    }

    public function test_delete_with_exams_soft_deletes(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $level = ClassLevel::firstWhere('name', 'JSS 1');

        $term = Term::create([
            'name' => 'First Term',
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-20',
            'academic_session_id' => AcademicSession::create([
                'name' => '2025/2026',
                'start_date' => '2025-09-01',
                'end_date' => '2026-08-31',
                'is_current' => true,
            ])->id,
            'is_current' => true,
        ]);

        Exam::create([
            'title' => 'Test Exam',
            'class_level_id' => $level->id,
            'subject_id' => Subject::create(['name' => 'Maths', 'code' => 'MATH'])->id,
            'term_id' => $term->id,
            'created_by' => $admin->id,
            'type' => 'exam',
            'status' => 'draft',
            'total_marks' => 100,
            'max_attempts' => 1,
            'duration_minutes' => 60,
            'pass_mark' => 50,
            'settings' => [],
        ]);

        $this->deleteJson("/api/class-levels/{$level->id}")->assertStatus(200);

        $this->assertSoftDeleted($level->fresh());
    }

    // --- Class Arm tests ---

    public function test_arm_same_name_in_different_levels_allowed(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $this->postJson('/api/class-levels', ['name' => 'JSS 2'])->assertStatus(201);

        $level1 = ClassLevel::firstWhere('name', 'JSS 1');
        $level2 = ClassLevel::firstWhere('name', 'JSS 2');

        $this->postJson("/api/class-levels/{$level1->id}/arms", ['name' => 'A'])->assertStatus(201);
        $this->postJson("/api/class-levels/{$level2->id}/arms", ['name' => 'A'])->assertStatus(201);
    }

    public function test_arm_duplicate_in_same_level_fails(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $level = ClassLevel::firstWhere('name', 'JSS 1');

        $this->postJson("/api/class-levels/{$level->id}/arms", ['name' => 'A'])->assertStatus(201);
        $this->postJson("/api/class-levels/{$level->id}/arms", ['name' => 'A'])->assertStatus(422);
    }

    public function test_arm_update_with_same_name_passes(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $level = ClassLevel::firstWhere('name', 'JSS 1');

        $this->postJson("/api/class-levels/{$level->id}/arms", ['name' => 'A'])->assertStatus(201);
        $arm = ClassArm::firstWhere('name', 'A');

        $this->patchJson("/api/class-levels/{$level->id}/arms/{$arm->id}", ['name' => 'A'])->assertStatus(200);
    }

    public function test_arm_update_to_conflicting_name_fails(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $level = ClassLevel::firstWhere('name', 'JSS 1');

        $this->postJson("/api/class-levels/{$level->id}/arms", ['name' => 'A'])->assertStatus(201);
        $this->postJson("/api/class-levels/{$level->id}/arms", ['name' => 'B'])->assertStatus(201);

        $armA = ClassArm::firstWhere('name', 'A');

        $this->patchJson("/api/class-levels/{$level->id}/arms/{$armA->id}", ['name' => 'B'])->assertStatus(422);
    }

    public function test_arm_delete_with_students_soft_deletes(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $level = ClassLevel::firstWhere('name', 'JSS 1');

        $this->postJson("/api/class-levels/{$level->id}/arms", ['name' => 'A'])->assertStatus(201);
        $arm = ClassArm::firstWhere('name', 'A');

        $student = User::create([
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => 'student2@test.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'is_active' => true,
        ]);
        StudentProfile::create([
            'user_id' => $student->id,
            'class_level_id' => $level->id,
            'class_arm_id' => $arm->id,
            'admission_number' => 'STU/2026/0002',
        ]);

        $this->deleteJson("/api/class-levels/{$level->id}/arms/{$arm->id}")->assertStatus(200);

        $this->assertSoftDeleted($arm->fresh());
    }

    public function test_arm_case_variant_duplicate(): void
    {
        $this->actingAs($this->createAdmin(), 'tenant');

        $this->postJson('/api/class-levels', ['name' => 'JSS 1'])->assertStatus(201);
        $level = ClassLevel::firstWhere('name', 'JSS 1');

        $this->postJson("/api/class-levels/{$level->id}/arms", ['name' => 'a'])->assertStatus(201);
        $this->postJson("/api/class-levels/{$level->id}/arms", ['name' => 'A'])->assertStatus(422);
    }
}
