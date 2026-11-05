<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\ExamAttendanceStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamResult;
use App\Models\Tenant\Question;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\TermResult;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentDeleteTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $student;

    protected Subject $subject;

    protected ClassLevel $classLevel;

    protected AcademicSession $academicSession;

    protected Term $term;

    protected Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::findOrCreate(RoleType::SchoolAdmin->value, 'tenant');
        Role::findOrCreate(RoleType::Student->value, 'tenant');

        $this->subject = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $this->classLevel = ClassLevel::create([
            'name' => 'Grade 10',
            'slug' => 'grade-10',
        ]);

        $classArm = ClassArm::create([
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
            'email' => 'admin-delete@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole(RoleType::SchoolAdmin->value);

        $this->student = User::create([
            'first_name' => 'Student',
            'last_name' => 'Delete',
            'email' => 'student-delete@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $this->student->assignRole(RoleType::Student->value);

        $this->exam = Exam::create([
            'title' => 'Delete Test Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $classArm->id,
            'term_id' => $this->term->id,
            'type' => ExamType::Exam->value,
            'status' => ExamStatus::Active->value,
            'duration_minutes' => 60,
            'total_marks' => 100,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'created_by' => $this->admin->id,
            'settings' => ['require_attendance' => false],
        ]);

        StudentProfile::create([
            'user_id' => $this->student->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $classArm->id,
            'admission_number' => 'STU/2026/0001',
        ]);

        Sanctum::actingAs($this->admin, ['*'], 'tenant');
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();

        parent::tearDown();
    }

    public function test_admin_can_permanently_delete_a_student_with_exam_records(): void
    {
        [$attempt, $question] = $this->createExamRecords($this->student);

        $this->student->createToken('student-token');

        $response = $this->deleteJson("/api/students/{$this->student->id}");

        $response->assertOk();
        $response->assertJsonPath('message', 'Student permanently deleted.');

        $this->assertDatabaseMissing('users', ['id' => $this->student->id]);
        $this->assertDatabaseMissing('student_profiles', ['user_id' => $this->student->id]);
        $this->assertDatabaseMissing('exam_attempts', ['id' => $attempt->id]);
        $this->assertDatabaseMissing('exam_answers', ['attempt_id' => $attempt->id]);
        $this->assertDatabaseMissing('exam_results', ['student_id' => $this->student->id]);
        $this->assertDatabaseMissing('exam_attendance', ['student_id' => $this->student->id]);
        $this->assertDatabaseMissing('term_results', ['student_id' => $this->student->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $this->student->id]);
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $this->student->id]);
    }

    public function test_deleting_a_student_recalculates_expected_attempts(): void
    {
        $this->assertSame(1, $this->exam->fresh()->expected_attempts);

        $this->createExamRecords($this->student);

        $this->deleteJson("/api/students/{$this->student->id}")->assertOk();

        $this->assertSame(0, $this->exam->fresh()->expected_attempts);
    }

    public function test_admin_can_delete_a_student_without_exam_records(): void
    {
        $this->deleteJson("/api/students/{$this->student->id}")->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $this->student->id]);
    }

    /**
     * @return array{0: ExamAttempt, 1: Question}
     */
    private function createExamRecords(User $student): array
    {
        $question = Question::create([
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->admin->id,
            'type' => QuestionType::Mcq->value,
            'content' => 'What is 2 + 2?',
            'default_marks' => 1,
            'is_active' => true,
        ]);

        $attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => ExamAttemptStatus::Graded->value,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'total_score' => 50,
            'percentage_score' => 50,
            'grade' => 'C',
        ]);

        DB::table('exam_answers')->insert([
            'id' => Str::orderedUuid()->toString(),
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answered_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ExamResult::create([
            'exam_attempt_id' => $attempt->id,
            'student_id' => $student->id,
            'exam_id' => $this->exam->id,
            'subject_id' => $this->subject->id,
            'term_id' => $this->term->id,
            'academic_session_id' => $this->academicSession->id,
            'total_score' => 50,
            'percentage_score' => 50,
            'grade' => 'C',
        ]);

        DB::table('exam_attendance')->insert([
            'id' => Str::orderedUuid()->toString(),
            'exam_id' => $this->exam->id,
            'student_id' => $student->id,
            'status' => ExamAttendanceStatus::Present->value,
            'marked_by' => $this->admin->id,
            'marked_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        TermResult::create([
            'student_id' => $student->id,
            'subject_id' => $this->subject->id,
            'term_id' => $this->term->id,
            'class_level_id' => $this->classLevel->id,
            'total_score' => 50,
            'grade' => 'C',
        ]);

        return [$attempt, $question];
    }
}
