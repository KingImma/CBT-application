<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminExamQuestionsTest extends TestCase
{
    protected Tenant $tenant;

    protected User $teacher;

    protected User $admin;

    protected User $student;

    protected Exam $exam;

    protected Question $question;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $subject = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $classLevel = ClassLevel::create([
            'name' => 'Grade 10',
            'slug' => 'grade-10',
        ]);

        $classArm = ClassArm::create([
            'name' => 'Grade 10 A',
            'class_level_id' => $classLevel->id,
        ]);

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

        $this->teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'One',
            'email' => 'teacher@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $this->teacher->assignRole('teacher');

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole('school_admin');

        $this->student = User::create([
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $this->student->assignRole('student');

        $this->exam = Exam::create([
            'title' => 'Mathematics Exam',
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'class_arm_id' => $classArm->id,
            'term_id' => $term->id,
            'created_by' => $this->teacher->id,
            'type' => ExamType::Exam->value,
            'status' => ExamStatus::Active->value,
            'duration_minutes' => 60,
            'total_marks' => 1,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'settings' => ['require_attendance' => false],
        ]);

        $this->question = Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'created_by' => $this->teacher->id,
            'type' => QuestionType::Mcq->value,
            'content' => 'What is 2 + 2?',
            'default_marks' => 1,
            'is_active' => true,
            'academic_session_id' => $academicSession->id,
            'term_id' => $term->id,
        ]);
        $this->question->options()->createMany([
            ['label' => 'A', 'content' => '3', 'is_correct' => false, 'order' => 1],
            ['label' => 'B', 'content' => '4', 'is_correct' => true, 'order' => 2],
            ['label' => 'C', 'content' => '5', 'is_correct' => false, 'order' => 3],
        ]);

        ExamQuestion::create([
            'exam_id' => $this->exam->id,
            'question_id' => $this->question->id,
            'order' => 1,
            'marks' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();

        parent::tearDown();
    }

    #[Test]
    public function teacher_can_fetch_exam_questions_with_correct_answers(): void
    {
        Sanctum::actingAs($this->teacher, ['*'], 'tenant');

        $response = $this->getJson("/api/exams/{$this->exam->id}/questions");

        $response
            ->assertSuccessful()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.question_id', $this->question->id)
            ->assertJsonPath('data.0.question.options.0.is_correct', false)
            ->assertJsonPath('data.0.question.options.1.is_correct', true)
            ->assertJsonPath('data.0.question.options.2.is_correct', false);
    }

    #[Test]
    public function school_admin_can_fetch_exam_questions(): void
    {
        Sanctum::actingAs($this->admin, ['*'], 'tenant');

        $response = $this->getJson("/api/exams/{$this->exam->id}/questions");

        $response
            ->assertSuccessful()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function student_cannot_fetch_exam_questions(): void
    {
        Sanctum::actingAs($this->student, ['*'], 'tenant');

        $response = $this->getJson("/api/exams/{$this->exam->id}/questions");

        $response->assertForbidden();
    }

    #[Test]
    public function fetch_questions_returns_correct_structure(): void
    {
        Sanctum::actingAs($this->teacher, ['*'], 'tenant');

        $response = $this->getJson("/api/exams/{$this->exam->id}/questions");

        $response
            ->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'exam_id',
                        'question_id',
                        'order',
                        'marks',
                        'question' => [
                            'id',
                            'type',
                            'content',
                            'image_url',
                            'options' => [
                                '*' => [
                                    'id',
                                    'label',
                                    'content',
                                    'image_url',
                                    'order',
                                    'is_correct',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }
}
