<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentExamSessionFlowTest extends TestCase
{
    protected Tenant $tenant;

    protected User $student;

    protected Exam $exam;

    protected ExamAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'tenant']);

        $teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'Session',
            'email' => 'teacher-session@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);

        $this->student = User::create([
            'first_name' => 'Student',
            'last_name' => 'Session',
            'email' => 'student-session@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $this->student->assignRole('student');

        $subject = Subject::create(['name' => 'General', 'code' => 'GEN']);
        $classLevel = ClassLevel::create(['name' => 'Grade 10', 'slug' => 'grade-10']);
        $academicSession = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'is_current' => true,
        ]);
        $term = Term::create([
            'name' => 'First Term',
            'academic_session_id' => $academicSession->id,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-20',
            'is_current' => true,
        ]);

        $this->exam = Exam::create([
            'title' => 'Session Flow Exam',
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'term_id' => $term->id,
            'created_by' => $teacher->id,
            'type' => ExamType::Exam->value,
            'status' => ExamStatus::Active->value,
            'duration_minutes' => 60,
            'total_marks' => 1,
            'pass_mark' => 50,
            'max_attempts' => 2,
            'scheduled_start' => now()->subHour(),
            'settings' => ['require_attendance' => false],
        ]);

        // A question so the exam has questions
        $question = Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'created_by' => $teacher->id,
            'type' => QuestionType::Mcq->value,
            'content' => 'Sample question?',
            'default_marks' => 1,
            'is_active' => true,
        ]);
        $question->options()->createMany([
            ['label' => 'A', 'content' => 'Yes', 'is_correct' => true, 'order' => 1],
            ['label' => 'B', 'content' => 'No', 'is_correct' => false, 'order' => 2],
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_id' => $question->id,
            'order' => 1,
            'marks' => 1,
        ]);

        Sanctum::actingAs($this->student, ['*'], 'tenant');
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();

        parent::tearDown();
    }

    public function test_student_can_start_exam(): void
    {
        $response = $this->postJson(
            "/api/student/exams/{$this->exam->id}/start",
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.attempt.exam_id', $this->exam->id);

        // Verify the attempt was created
        $attempt = ExamAttempt::where('exam_id', $this->exam->id)
            ->where('student_id', $this->student->id)
            ->first();

        $this->assertNotNull($attempt);
        $this->assertEquals(ExamAttemptStatus::InProgress->value, $attempt->status);
    }

    public function test_student_cannot_start_twice(): void
    {
        // First start creates the attempt
        $this->postJson("/api/student/exams/{$this->exam->id}/start");

        // Second start should fail
        $response = $this->postJson(
            "/api/student/exams/{$this->exam->id}/start",
        );

        $response->assertStatus(422);
    }

    public function test_student_can_resume_active_attempt(): void
    {
        // Start the exam
        $startResponse = $this->postJson(
            "/api/student/exams/{$this->exam->id}/start",
        );
        $attemptId = $startResponse->json('data.attempt.id');

        // Resume
        $response = $this->getJson(
            "/api/student/exams/{$this->exam->id}/attempt",
        );

        $response->assertSuccessful()
            ->assertJsonPath('data.attempt.id', $attemptId)
            ->assertJsonPath('data.attempt.status', ExamAttemptStatus::InProgress->value);
    }

    public function test_student_can_check_time_remaining(): void
    {
        // Start exam first
        $startResponse = $this->postJson(
            "/api/student/exams/{$this->exam->id}/start",
        );
        $attemptId = $startResponse->json('data.attempt.id');

        $response = $this->getJson(
            "/api/student/exams/attempts/{$attemptId}/time-remaining",
        );

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => ['remaining_seconds', 'expired'],
            ])
            ->assertJsonPath('data.expired', false);
    }

    public function test_student_can_get_session_state(): void
    {
        // Start exam first
        $startResponse = $this->postJson(
            "/api/student/exams/{$this->exam->id}/start",
        );
        $attemptId = $startResponse->json('data.attempt.id');

        $response = $this->getJson(
            "/api/student/exams/attempts/{$attemptId}/session-state",
        );

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'attempt_id',
                    'time_remaining_seconds',
                    'last_answer_id',
                    'last_activity_at',
                    'connection_alive',
                ],
            ]);
    }
}
