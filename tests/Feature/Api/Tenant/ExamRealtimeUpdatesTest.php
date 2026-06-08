<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\ExamStatus;
use App\Enums\RoleType;
use App\Events\ExamAttemptsUpdated;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use App\Models\Tenant\Subject;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExamRealtimeUpdatesTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $teacher;

    protected User $student;

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
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole('school_admin');

        $this->teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'One',
            'email' => 'teacher@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $this->teacher->assignRole('teacher');

        $this->student = User::create([
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $this->student->assignRole('student');
        $this->student->studentProfile()->create([
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'guardian_email' => 'guardian@example.com',
            'admission_number' => 'STU001',
        ]);

        TeacherSubjectAssignment::create([
            'user_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'academic_session_id' => $this->academicSession->id,
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

    protected function createDraftExam(?User $asUser = null, array $overrides = []): Exam
    {
        $user = $asUser ?? $this->admin;
        $this->actingAsTenant($user);

        return Exam::create(array_merge([
            'title' => 'Test Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => 'draft',
            'duration_minutes' => 60,
            'pass_mark' => 5.00,
            'total_marks' => 0,
            'max_attempts' => 1,
            'created_by' => $user->id,
            'scheduled_start' => now()->subHour(),
            'settings' => ['require_attendance' => false],
        ], $overrides));
    }

    protected function addQuestionToExam(Exam $exam, ?User $owner = null): Question
    {
        $user = $owner ?? $this->teacher;
        $question = Question::create([
            'content' => 'What is 2+2?',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $user->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);

        $exam->examQuestions()->create([
            'question_id' => $question->id,
            'order' => $exam->examQuestions()->max('order') + 1,
            'marks' => 5.00,
        ]);

        $exam->update(['total_marks' => $exam->examQuestions()->sum('marks')]);

        return $question;
    }

    #[Test]
    public function exam_list_response_includes_attempt_counts_and_question_count(): void
    {
        $exam = $this->createDraftExam();
        $this->actingAsTenant($this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/activate")->assertStatus(200);

        $response = $this->getJson('/api/exams');
        $response->assertStatus(200);

        $examData = collect($response->json('data'))->firstWhere('id', $exam->id);
        $this->assertNotNull($examData);
        $this->assertArrayHasKey('question_count', $examData);
        $this->assertArrayHasKey('expected_attempts', $examData);
        $this->assertArrayHasKey('completed_attempts', $examData);
        $this->assertEquals(1, $examData['question_count']);
        $this->assertEquals(1, $examData['expected_attempts']);
        $this->assertEquals(0, $examData['completed_attempts']);
    }

    #[Test]
    public function exam_show_response_includes_attempt_counts_and_question_count(): void
    {
        $exam = $this->createDraftExam();
        $this->actingAsTenant($this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/activate")->assertStatus(200);

        $response = $this->getJson("/api/exams/{$exam->id}");
        $response->assertStatus(200);

        $examData = $response->json('data');
        $this->assertArrayHasKey('question_count', $examData);
        $this->assertArrayHasKey('expected_attempts', $examData);
        $this->assertArrayHasKey('completed_attempts', $examData);
        $this->assertEquals(1, $examData['question_count']);
        $this->assertEquals(1, $examData['expected_attempts']);
        $this->assertEquals(0, $examData['completed_attempts']);
    }

    #[Test]
    public function exam_auto_completes_when_all_students_submit(): void
    {
        Event::fake();

        $exam = $this->createDraftExam();
        $this->actingAsTenant($this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/activate")->assertStatus(200);

        $exam->fresh();
        $this->assertEquals(1, $exam->fresh()->expected_attempts);

        $this->actingAsTenant($this->student);
        $response = $this->postJson("/student/exams/{$exam->id}/start");
        $response->assertStatus(201);
        $attemptId = $response->json('data.attempt.id');

        $response = $this->postJson("/student/exams/attempts/{$attemptId}/submit");
        $response->assertStatus(200);

        $this->assertEquals(ExamStatus::Completed, $exam->fresh()->status);
        $this->assertEquals(1, $exam->fresh()->completed_attempts);

        Event::assertDispatched(ExamAttemptsUpdated::class, function ($event) use ($exam) {
            return $event->examId === $exam->id
                && $event->completedAttempts === 1
                && $event->expectedAttempts === 1
                && $event->status === 'completed';
        });
    }
}
