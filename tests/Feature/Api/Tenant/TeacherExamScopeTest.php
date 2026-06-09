<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TeacherExamScopeTest extends TestCase
{
    protected Tenant $tenant;

    protected User $teacherA;

    protected User $teacherB;

    protected User $admin;

    protected Subject $subject;

    protected ClassLevel $classLevel;

    protected ClassArm $classArm;

    protected AcademicSession $academicSession;

    protected Term $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->subject = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $this->classLevel = ClassLevel::create([
            'name' => 'Grade 10',
            'slug' => 'grade-10',
        ]);

        $this->classArm = ClassArm::create([
            'name' => 'Grade 10 A',
            'class_level_id' => $this->classLevel->id,
        ]);

        $this->academicSession = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'is_current' => true,
        ]);

        $this->term = Term::create([
            'name' => 'First Term',
            'academic_session_id' => $this->academicSession->id,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-20',
            'is_current' => true,
        ]);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole('school_admin');

        $this->teacherA = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'A',
            'email' => 'teacher.a@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $this->teacherA->assignRole('teacher');

        $this->teacherB = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'B',
            'email' => 'teacher.b@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $this->teacherB->assignRole('teacher');
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    protected function actingAsTenant(User $user): static
    {
        Sanctum::actingAs($user, ['*'], 'tenant');

        return $this;
    }

    #[Test]
    public function teacher_sees_only_own_exams_in_list(): void
    {
        $this->actingAsTenant($this->teacherA);
        Exam::create([
            'title' => 'Teacher A Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => 'draft',
            'duration_minutes' => 60,
            'pass_mark' => 50.00,
            'total_marks' => 0,
            'max_attempts' => 1,
            'created_by' => $this->teacherA->id,
            'settings' => ['require_attendance' => false],
        ]);

        $this->actingAsTenant($this->teacherB);
        Exam::create([
            'title' => 'Teacher B Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => 'draft',
            'duration_minutes' => 60,
            'pass_mark' => 50.00,
            'total_marks' => 0,
            'max_attempts' => 1,
            'created_by' => $this->teacherB->id,
            'settings' => ['require_attendance' => false],
        ]);

        // Teacher A sees only their own exam
        $this->actingAsTenant($this->teacherA);
        $response = $this->getJson('/api/exams');
        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertCount(1, $titles);
        $this->assertContains('Teacher A Exam', $titles);
        $this->assertNotContains('Teacher B Exam', $titles);

        // Teacher B sees only their own exam
        $this->actingAsTenant($this->teacherB);
        $response = $this->getJson('/api/exams');
        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertCount(1, $titles);
        $this->assertContains('Teacher B Exam', $titles);
        $this->assertNotContains('Teacher A Exam', $titles);
    }

    #[Test]
    public function admin_sees_all_exams(): void
    {
        $this->actingAsTenant($this->teacherA);
        Exam::create([
            'title' => 'Teacher A Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => 'draft',
            'duration_minutes' => 60,
            'pass_mark' => 50.00,
            'total_marks' => 0,
            'max_attempts' => 1,
            'created_by' => $this->teacherA->id,
            'settings' => ['require_attendance' => false],
        ]);

        $this->actingAsTenant($this->teacherB);
        Exam::create([
            'title' => 'Teacher B Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => 'draft',
            'duration_minutes' => 60,
            'pass_mark' => 50.00,
            'total_marks' => 0,
            'max_attempts' => 1,
            'created_by' => $this->teacherB->id,
            'settings' => ['require_attendance' => false],
        ]);

        $this->actingAsTenant($this->admin);
        $response = $this->getJson('/api/exams');
        $response->assertStatus(200);
        $titles = collect($response->json('data'))->pluck('title');
        $this->assertCount(2, $titles);
        $this->assertContains('Teacher A Exam', $titles);
        $this->assertContains('Teacher B Exam', $titles);
    }
}
