<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Domains\Exams\Actions\Results\BuildStudentCumulativeResult;
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
use App\Models\Tenant\ExamResult;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherExamReportPdfTest extends TestCase
{
    protected Tenant $tenant;

    protected Subject $subject;

    protected ClassLevel $classLevel;

    protected ClassArm $classArm;

    protected AcademicSession $academicSession;

    protected Term $term;

    protected User $admin;

    protected User $teacher;

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

        DB::table('class_level_subject')->insert([
            'id' => Str::orderedUuid()->toString(),
            'class_level_id' => $this->classLevel->id,
            'subject_id' => $this->subject->id,
            'is_compulsory' => true,
        ]);

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

        $this->teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'A',
            'email' => 'teacher.a@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $this->teacher->assignRole('teacher');

        $this->classArm->update(['assigned_teacher_id' => $this->teacher->id]);

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
            'created_by' => $this->teacher->id,
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

    private function createStudent(string $email, string $firstName = 'Student', string $admissionNumber = 'STU001'): User
    {
        $user = User::create([
            'first_name' => $firstName,
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $user->assignRole('student');

        StudentProfile::create([
            'user_id' => $user->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'admission_number' => $admissionNumber,
            'guardian_email' => 'guardian@test.com',
        ]);

        return $user;
    }

    private function createGradedAttempt(User $student, float $totalScore = 75.00): ExamAttempt
    {
        return ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => ExamAttemptStatus::Graded->value,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'total_score' => $totalScore,
            'percentage_score' => $totalScore,
            'grade' => $totalScore >= 50 ? 'B' : 'F',
        ]);
    }

    private function createExamResult(ExamAttempt $attempt, User $student, float $totalScore): ExamResult
    {
        return ExamResult::create([
            'exam_attempt_id' => $attempt->id,
            'student_id' => $student->id,
            'exam_id' => $this->exam->id,
            'subject_id' => $this->subject->id,
            'term_id' => $this->term->id,
            'academic_session_id' => $this->academicSession->id,
            'total_score' => $totalScore,
            'percentage_score' => $totalScore,
            'grade' => $totalScore >= 50 ? 'B' : 'F',
        ]);
    }

    #[Test]
    public function cumulative_result_pdf_downloads_for_a_student(): void
    {
        $student = $this->createStudent('student@test.com', 'Alice', 'STU001');
        $attempt = $this->createGradedAttempt($student, 75.00);
        $this->createExamResult($attempt, $student, 75.00);

        $this->actingAsTenant($this->admin);

        $response = $this->get("/api/students/{$student->id}/cumulative-result/pdf?academic_session_id={$this->academicSession->id}");

        $response->assertOk();
        $response->assertDownload();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('content-type'));
    }

    #[Test]
    public function exam_summary_pdf_downloads_for_a_class_and_exam(): void
    {
        $student = $this->createStudent('student@test.com', 'Alice', 'STU001');
        $this->createGradedAttempt($student, 75.00);

        $this->actingAsTenant($this->teacher);

        $response = $this->get("/api/class-arms/{$this->classArm->id}/exams/{$this->exam->id}/report/pdf");

        $response->assertOk();
        $response->assertDownload();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('content-type'));
    }

    #[Test]
    public function exam_summary_pdf_is_denied_for_unrelated_teacher(): void
    {
        $student = $this->createStudent('student@test.com', 'Alice', 'STU001');
        $this->createGradedAttempt($student, 75.00);

        $otherTeacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'B',
            'email' => 'teacher.b@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $otherTeacher->assignRole('teacher');

        $this->actingAsTenant($otherTeacher);

        $this->get("/api/class-arms/{$this->classArm->id}/exams/{$this->exam->id}/report/pdf")
            ->assertForbidden();
    }

    #[Test]
    public function class_cumulative_pdf_downloads_sheets_for_every_student_in_arm(): void
    {
        $student = $this->createStudent('student@test.com', 'Alice', 'STU001');
        $attempt = $this->createGradedAttempt($student, 75.00);
        $this->createExamResult($attempt, $student, 75.00);

        $this->actingAsTenant($this->admin);

        $response = $this->get("/api/class-arms/{$this->classArm->id}/exams/{$this->exam->id}/report/pdf");

        $response->assertOk();
        $response->assertDownload();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('content-type'));
    }

    #[Test]
    public function cumulative_result_sheet_lists_subjects_the_student_was_graded_in(): void
    {
        $student = $this->createStudent('student@test.com', 'Alice', 'STU001');
        $attempt = $this->createGradedAttempt($student, 75.00);
        $this->createExamResult($attempt, $student, 75.00);

        $result = app(BuildStudentCumulativeResult::class)->execute($student, $this->academicSession);

        $html = view('pdf.exam-cumulative-result', [
            'results' => collect([$result]),
            'school' => ['name' => 'Test School', 'address' => null, 'motto' => null, 'logo' => null],
        ])->render();

        $this->assertStringContainsString('MATHEMATICS', $html);
        $this->assertStringContainsString('75', $html);
        $this->assertStringNotContainsString('No results recorded for this term.', $html);
    }

    #[Test]
    public function broadsheet_pdf_downloads_for_a_class(): void
    {
        $student = $this->createStudent('student@test.com', 'Alice', 'STU001');
        $attempt = $this->createGradedAttempt($student, 75.00);
        $this->createExamResult($attempt, $student, 75.00);

        $this->actingAsTenant($this->admin);

        $response = $this->get('/api/broadsheet/pdf'.'?'.http_build_query([
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'academic_session_id' => $this->academicSession->id,
        ]));

        $response->assertOk();
        $response->assertDownload();
        $this->assertStringStartsWith('application/pdf', $response->headers->get('content-type'));
    }
}
