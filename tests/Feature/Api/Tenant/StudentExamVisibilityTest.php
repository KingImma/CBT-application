<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\ExamStatus;
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

class StudentExamVisibilityTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $studentOne;

    protected User $studentTwo;

    protected Subject $subject;

    protected ClassLevel $jss1;

    protected ClassLevel $jss2;

    protected ClassArm $armA;

    protected ClassArm $armB;

    protected Term $term;

    protected Exam $jss1Exam;

    protected Exam $jss2Exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH101']);
        $this->jss1 = ClassLevel::create(['name' => 'JSS 1', 'slug' => 'jss-1']);
        $this->jss2 = ClassLevel::create(['name' => 'JSS 2', 'slug' => 'jss-2']);
        $this->armA = ClassArm::create(['name' => 'JSS 1 A', 'class_level_id' => $this->jss1->id]);
        $this->armB = ClassArm::create(['name' => 'JSS 2 A', 'class_level_id' => $this->jss2->id]);

        $session = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'is_current' => true,
        ]);

        $this->term = Term::create([
            'name' => 'First Term',
            'academic_session_id' => $session->id,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-20',
            'is_current' => true,
        ]);

        $this->admin = $this->makeUser('admin@test.com', RoleType::SchoolAdmin);
        $this->studentOne = $this->makeStudent('student-one@test.com', $this->jss1->id, $this->armA->id, 'STU001');
        $this->studentTwo = $this->makeStudent('student-two@test.com', $this->jss2->id, $this->armB->id, 'STU002');

        $this->jss1Exam = $this->makeExam('JSS 1 Maths', $this->jss1->id, $this->armA->id);
        $this->jss2Exam = $this->makeExam('JSS 2 Maths', $this->jss2->id, $this->armB->id);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    #[Test]
    public function each_student_sees_only_exams_for_their_own_class(): void
    {
        Sanctum::actingAs($this->studentOne, ['*'], 'tenant');

        $response = $this->getJson('/api/student/exams/available')->assertSuccessful();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->jss1Exam->id));
        $this->assertFalse($ids->contains($this->jss2Exam->id));

        Sanctum::actingAs($this->studentTwo, ['*'], 'tenant');

        $response = $this->getJson('/api/student/exams/available')->assertSuccessful();

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($this->jss2Exam->id));
        $this->assertFalse($ids->contains($this->jss1Exam->id));
    }

    private function makeUser(string $email, RoleType $role): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role->value,
            'is_active' => true,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function makeStudent(string $email, string $levelId, string $armId, string $admissionNumber): User
    {
        $student = $this->makeUser($email, RoleType::Student);
        $student->studentProfile()->create([
            'class_level_id' => $levelId,
            'class_arm_id' => $armId,
            'guardian_email' => 'guardian@test.com',
            'admission_number' => $admissionNumber,
        ]);

        return $student;
    }

    private function makeExam(string $title, string $levelId, string $armId): Exam
    {
        return Exam::create([
            'title' => $title,
            'subject_id' => $this->subject->id,
            'class_level_id' => $levelId,
            'class_arm_id' => $armId,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => ExamStatus::Active->value,
            'duration_minutes' => 60,
            'total_marks' => 100,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'created_by' => $this->admin->id,
            'settings' => ['require_attendance' => false],
        ]);
    }
}
