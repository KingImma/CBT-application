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

class StudentExamQuestionsTest extends TestCase
{
    protected Tenant $tenant;

    protected User $student;

    protected User $otherStudent;

    protected Exam $exam;

    protected ExamAttempt $attempt;

    protected Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'tenant']);

        $teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'One',
            'email' => 'teacher@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);

        $this->student = User::create([
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $this->student->assignRole('student');

        $this->otherStudent = User::create([
            'first_name' => 'Student',
            'last_name' => 'Two',
            'email' => 'other-student@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $this->otherStudent->assignRole('student');

        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MTH']);
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
            'title' => 'Mathematics Exam',
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'term_id' => $term->id,
            'created_by' => $teacher->id,
            'type' => ExamType::Exam->value,
            'status' => ExamStatus::Active->value,
            'duration_minutes' => 60,
            'total_marks' => 1,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'session_started_at' => now(),
            'session_duration_minutes' => 60,
            'settings' => ['require_attendance' => false],
        ]);

        $this->question = Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'created_by' => $teacher->id,
            'type' => QuestionType::Mcq->value,
            'content' => 'What is 2 + 2?',
            'default_marks' => 1,
            'is_active' => true,
        ]);
        $this->question->options()->createMany([
            ['label' => 'A', 'content' => '3', 'is_correct' => false, 'order' => 1],
            ['label' => 'B', 'content' => '4', 'is_correct' => true, 'order' => 2],
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_id' => $this->question->id,
            'order' => 1,
            'marks' => 1,
        ]);

        $this->attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => ExamAttemptStatus::InProgress->value,
            'started_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();

        parent::tearDown();
    }

    public function test_student_can_call_questions_for_active_exam_attempt_without_answers(): void
    {
        Sanctum::actingAs($this->student, ['*'], 'tenant');

        $response = $this->getJson("/api/student/exams/{$this->exam->id}/questions");

        $response
            ->assertSuccessful()
            ->assertJsonPath('data.exam_id', $this->exam->id)
            ->assertJsonPath('data.attempt_id', $this->attempt->id)
            ->assertJsonPath('data.questions.0.question_id', $this->question->id)
            ->assertJsonPath('data.questions.0.type', 'mcq')
            ->assertJsonPath('data.questions.0.content', 'What is 2 + 2?')
            ->assertJsonMissingPath('data.questions.0.options.0.is_correct');
    }

    public function test_student_cannot_call_another_students_attempt_questions(): void
    {
        Sanctum::actingAs($this->otherStudent, ['*'], 'tenant');

        $this->getJson("/api/student/exams/attempts/{$this->attempt->id}/questions")
            ->assertForbidden();
    }

    public function test_exam_questions_endpoint_requires_an_active_attempt(): void
    {
        Sanctum::actingAs($this->otherStudent, ['*'], 'tenant');

        $this->getJson("/api/student/exams/{$this->exam->id}/questions")
            ->assertNotFound();
    }
}
