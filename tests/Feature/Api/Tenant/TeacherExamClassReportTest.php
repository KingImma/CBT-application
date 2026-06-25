<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\RoleType;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\Subject;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherExamClassReportTest extends TestCase
{
    protected Tenant $tenant;

    protected Subject $subject;

    protected ClassLevel $classLevel;

    protected ClassArm $classArm;

    protected AcademicSession $academicSession;

    protected Term $term;

    protected User $admin;

    protected User $teacherA;

    protected User $teacherB;

    protected Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = SubscriptionPlan::factory()->create();
        $uniqueId = Str::orderedUuid()->toString();
        $this->tenant = Tenant::factory()->create([
            'plan_id' => $plan->id,
            'id' => $uniqueId,
            'slug' => $uniqueId,
            'handle' => $uniqueId,
            'database' => 'tenant_'.str_replace('-', '_', $uniqueId),
        ]);
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

        // Ensure the teacher role and view_results permission exist
        $teacherRole = Role::findOrCreate('teacher', 'tenant');
        Permission::findOrCreate('view_results', 'tenant');
        $teacherRole->givePermissionTo('view_results');

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

        // Assign teacherA as the class teacher (homeroom)
        $this->classArm->update(['assigned_teacher_id' => $this->teacherA->id]);

        $this->exam = Exam::create([
            'title' => 'Math Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => ExamStatus::Completed->value,
            'duration_minutes' => 60,
            'pass_mark' => 50.00,
            'total_marks' => 100,
            'max_attempts' => 1,
            'created_by' => $this->teacherA->id,
            'settings' => ['require_attendance' => false],
        ]);
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

    private function createStudentUser(string $email, string $firstName = 'Student', string $lastName = 'User'): User
    {
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $user->assignRole('student');

        return $user;
    }

    private function createStudentProfile(User $studentUser, string $admissionNumber): StudentProfile
    {
        return StudentProfile::create([
            'user_id' => $studentUser->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'admission_number' => $admissionNumber,
            'guardian_email' => 'guardian@test.com',
        ]);
    }

    private function createGradedAttempt(User $studentUser, Exam $exam, float $totalScore, float $percentage, string $grade): ExamAttempt
    {
        return ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $studentUser->id,
            'attempt_number' => 1,
            'status' => ExamAttemptStatus::Graded->value,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'total_score' => $totalScore,
            'percentage_score' => $percentage,
            'grade' => $grade,
        ]);
    }

    #[Test]
    public function class_teacher_gets_report_for_own_class(): void
    {
        $studentUser = $this->createStudentUser('student@test.com');
        $this->createStudentProfile($studentUser, 'STU001');
        $this->createGradedAttempt($studentUser, $this->exam, 75.00, 75.00, 'B');

        $this->actingAsTenant($this->teacherA);

        $response = $this->getJson("/api/class-arms/{$this->classArm->id}/exams/{$this->exam->id}/report");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'summary' => [
                    'exam_id',
                    'exam_name',
                    'class_arm_id',
                    'class_arm_name',
                    'students_in_class',
                    'students_sat',
                    'average_score',
                    'highest_score',
                    'lowest_score',
                    'pass_count',
                    'fail_count',
                    'completion_status',
                    'completion_rate',
                    'exam_status',
                ],
                'students' => [
                    '*' => [
                        'student_id',
                        'student_name',
                        'score',
                        'percentage',
                        'grade',
                        'result_status',
                        'submitted_at',
                        'completed_at',
                    ],
                ],
            ],
        ]);

        $this->assertTrue($response->json('success'));
    }

    #[Test]
    public function subject_teacher_gets_report_for_matching_subject(): void
    {
        // Assign teacherB as a subject teacher for this subject/level
        TeacherSubjectAssignment::create([
            'user_id' => $this->teacherB->id,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'academic_session_id' => $this->academicSession->id,
        ]);

        $studentUser = $this->createStudentUser('student@test.com');
        $this->createStudentProfile($studentUser, 'STU001');
        $this->createGradedAttempt($studentUser, $this->exam, 75.00, 75.00, 'B');

        $this->actingAsTenant($this->teacherB);

        $response = $this->getJson("/api/class-arms/{$this->classArm->id}/exams/{$this->exam->id}/report");

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }

    #[Test]
    public function unauthorized_teacher_gets_403(): void
    {
        $studentUser = $this->createStudentUser('student@test.com');
        $this->createStudentProfile($studentUser, 'STU001');
        $this->createGradedAttempt($studentUser, $this->exam, 75.00, 75.00, 'B');

        // teacherB has no relation to this class arm or exam
        $this->actingAsTenant($this->teacherB);

        $response = $this->getJson("/api/class-arms/{$this->classArm->id}/exams/{$this->exam->id}/report");

        $response->assertStatus(403);
    }

    #[Test]
    public function active_exam_returns_422(): void
    {
        $activeExam = Exam::create([
            'title' => 'Active Math Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => ExamStatus::Active->value,
            'duration_minutes' => 60,
            'pass_mark' => 50.00,
            'total_marks' => 100,
            'max_attempts' => 1,
            'created_by' => $this->teacherA->id,
            'settings' => ['require_attendance' => false],
        ]);

        $this->actingAsTenant($this->teacherA);

        $response = $this->getJson("/api/class-arms/{$this->classArm->id}/exams/{$activeExam->id}/report");

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Exam results are not available yet.',
        ]);
    }

    #[Test]
    public function summary_metrics_are_computed_correctly(): void
    {
        // Student 1: passed (80 >= 50)
        $student1 = $this->createStudentUser('student1@test.com', 'Alice', 'Smith');
        $this->createStudentProfile($student1, 'STU001');
        $this->createGradedAttempt($student1, $this->exam, 80.00, 80.00, 'A');

        // Student 2: failed (30 < 50)
        $student2 = $this->createStudentUser('student2@test.com', 'Bob', 'Jones');
        $this->createStudentProfile($student2, 'STU002');
        $this->createGradedAttempt($student2, $this->exam, 30.00, 30.00, 'F');

        // Student 3: passed (60 >= 50)
        $student3 = $this->createStudentUser('student3@test.com', 'Carol', 'Brown');
        $this->createStudentProfile($student3, 'STU003');
        $this->createGradedAttempt($student3, $this->exam, 60.00, 60.00, 'C');

        // Student 4: no attempt (non-sitter)
        $student4 = $this->createStudentUser('student4@test.com', 'David', 'Wilson');
        $this->createStudentProfile($student4, 'STU004');

        $this->actingAsTenant($this->teacherA);

        $response = $this->getJson("/api/class-arms/{$this->classArm->id}/exams/{$this->exam->id}/report");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $summary = $response->json('data.summary');

        $this->assertSame(4, $summary['students_in_class']);
        $this->assertSame(3, $summary['students_sat']);

        // Average of [80.00, 30.00, 60.00] = 56.67
        $this->assertEquals(56.67, $summary['average_score']);
        $this->assertEquals(80.0, $summary['highest_score']);
        $this->assertEquals(30.0, $summary['lowest_score']);

        $this->assertSame(2, $summary['pass_count']);
        $this->assertSame(1, $summary['fail_count']);

        $this->assertSame('partial', $summary['completion_status']);
        $this->assertEqualsWithDelta(75.0, $summary['completion_rate'], 0.01);
        $this->assertSame('completed', $summary['exam_status']);
    }

    #[Test]
    public function non_sitter_appears_with_not_attempted(): void
    {
        $sitter = $this->createStudentUser('sitter@test.com', 'Sitter', 'Student');
        $this->createStudentProfile($sitter, 'STU001');
        $this->createGradedAttempt($sitter, $this->exam, 75.00, 75.00, 'B');

        $nonSitter = $this->createStudentUser('non.sitter@test.com', 'NonSitter', 'Student');
        $this->createStudentProfile($nonSitter, 'STU002');

        $this->actingAsTenant($this->teacherA);

        $response = $this->getJson("/api/class-arms/{$this->classArm->id}/exams/{$this->exam->id}/report");

        $response->assertStatus(200);

        $students = $response->json('data.students');

        $nonSitterRow = collect($students)->firstWhere('student_id', $nonSitter->id);
        $this->assertNotNull($nonSitterRow);
        $this->assertSame('not_attempted', $nonSitterRow['result_status']);
        $this->assertNull($nonSitterRow['score']);
        $this->assertNull($nonSitterRow['percentage']);
        $this->assertNull($nonSitterRow['grade']);
    }

    #[Test]
    public function school_admin_bypasses_policy(): void
    {
        $studentUser = $this->createStudentUser('student@test.com');
        $this->createStudentProfile($studentUser, 'STU001');
        $this->createGradedAttempt($studentUser, $this->exam, 75.00, 75.00, 'B');

        $this->actingAsTenant($this->admin);

        $response = $this->getJson("/api/class-arms/{$this->classArm->id}/exams/{$this->exam->id}/report");

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
    }
}
