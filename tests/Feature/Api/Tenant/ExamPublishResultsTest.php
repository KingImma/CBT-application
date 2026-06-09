<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\ExamStatus;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Tests\TestCase;

class ExamPublishResultsTest extends TestCase
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

    private function createExam(array $overrides = []): Exam
    {
        $subject = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $classLevel = ClassLevel::create([
            'name' => 'Grade 10',
            'slug' => 'grade-10',
        ]);

        $academicSession = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => now()->subMonths(3),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);

        $term = Term::create([
            'name' => 'First Term',
            'academic_session_id' => $academicSession->id,
            'start_date' => now()->subMonths(3),
            'end_date' => now()->addMonths(3),
            'is_current' => true,
        ]);

        return Exam::create(array_merge([
            'title' => 'Test Exam',
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'term_id' => $term->id,
            'type' => 'exam',
            'status' => 'completed',
            'duration_minutes' => 60,
            'total_marks' => 100,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'settings' => '{"randomize_questions":false,"show_result_immediately":false,"results_release_date":null,"require_attendance":true,"max_suspicious_events":5}',
            'created_by' => User::create([
                'first_name' => 'Creator',
                'last_name' => 'User',
                'email' => 'creator@test.com',
                'password' => bcrypt('password'),
                'role' => 'teacher',
                'is_active' => true,
            ])->id,
        ], $overrides));
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

    private function createTeacher(): User
    {
        $teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'User',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
            'role' => 'teacher',
            'is_active' => true,
        ]);
        $teacher->assignRole('teacher');

        return $teacher;
    }

    private function createStudent(): User
    {
        $student = User::create([
            'first_name' => 'Student',
            'last_name' => 'User',
            'email' => 'student@test.com',
            'password' => bcrypt('password'),
            'role' => 'student',
            'is_active' => true,
        ]);
        $student->assignRole('student');

        return $student;
    }

    private function createGradedAttempt(Exam $exam, User $student): ExamAttempt
    {
        $question = Question::create([
            'content' => 'Sample question',
            'type' => 'mcq_single',
            'default_marks' => 10,
            'subject_id' => $exam->subject_id,
            'class_level_id' => $exam->class_level_id,
            'created_by' => $student->id,
        ]);

        $option = QuestionOption::create([
            'question_id' => $question->id,
            'content' => 'Correct answer',
            'is_correct' => true,
            'order' => 1,
        ]);

        $examQuestion = $exam->examQuestions()->create([
            'question_id' => $question->id,
            'order' => 1,
            'marks' => 10,
        ]);

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => 'graded',
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(30),
            'total_score' => 10,
            'percentage_score' => 10,
            'time_spent_seconds' => 1800,
        ]);

        $attempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_ids' => [$option->id],
            'is_correct' => true,
            'marks_awarded' => 10,
        ]);

        return $attempt;
    }

    // --- Admin publish tests ---

    public function test_admin_can_publish_results(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'tenant');

        $exam = $this->createExam();
        $this->assertEquals(ExamStatus::Completed, $exam->status);
        $this->assertNull($exam->published_at);
        $this->assertFalse($exam->isPublished());

        $response = $this->postJson("/api/exams/{$exam->id}/publish-results");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Results published.');
        $this->assertEquals('published', $response->json('data.status'));
        $this->assertNotNull($response->json('data.published_at'));
        $this->assertTrue($response->json('data.is_published'));

        $exam->refresh();
        $this->assertEquals(ExamStatus::Published, $exam->status);
        $this->assertNotNull($exam->published_at);
        $this->assertTrue($exam->isPublished());
    }

    public function test_admin_cannot_publish_results_twice(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'tenant');

        $exam = $this->createExam();
        $exam->publish();
        $exam->save();
        $exam->refresh();
        $this->assertTrue($exam->isPublished());

        $response = $this->postJson("/api/exams/{$exam->id}/publish-results");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'An exam can only be published once it is completed.');
    }

    public function test_teacher_cannot_publish_results(): void
    {
        $teacher = $this->createTeacher();
        $this->actingAs($teacher, 'tenant');

        $exam = $this->createExam();

        $response = $this->postJson("/api/exams/{$exam->id}/publish-results");

        $response->assertStatus(403);
    }

    public function test_student_cannot_publish_results(): void
    {
        $student = $this->createStudent();
        $this->actingAs($student, 'tenant');

        $exam = $this->createExam();

        $response = $this->postJson("/api/exams/{$exam->id}/publish-results");

        $response->assertStatus(403);
    }

    // --- Admin unpublish tests ---

    public function test_admin_can_unpublish_results(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'tenant');

        $exam = $this->createExam();
        $exam->publish();
        $exam->save();
        $exam->refresh();
        $this->assertTrue($exam->isPublished());
        $this->assertEquals(ExamStatus::Published, $exam->status);

        $response = $this->postJson("/api/exams/{$exam->id}/unpublish-results");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Results unpublished.');
        $this->assertEquals('completed', $response->json('data.status'));
        $this->assertNull($response->json('data.published_at'));
        $this->assertFalse($response->json('data.is_published'));

        $exam->refresh();
        $this->assertEquals(ExamStatus::Completed, $exam->status);
        $this->assertNull($exam->published_at);
        $this->assertFalse($exam->isPublished());
    }

    public function test_teacher_cannot_unpublish_results(): void
    {
        $teacher = $this->createTeacher();
        $this->actingAs($teacher, 'tenant');

        $exam = $this->createExam();

        $response = $this->postJson("/api/exams/{$exam->id}/unpublish-results");

        $response->assertStatus(403);
    }

    // --- Student result access tests ---

    public function test_student_cannot_access_unpublished_results(): void
    {
        $exam = $this->createExam();
        $student = $this->createStudent();
        $this->actingAs($student, 'tenant');

        $attempt = $this->createGradedAttempt($exam, $student);

        $response = $this->getJson("/api/student/exams/attempts/{$attempt->id}/result");

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'Results for this exam have not been released yet.');
    }

    public function test_student_can_access_published_results(): void
    {
        $exam = $this->createExam();
        $exam->publish();
        $exam->save();
        $exam->refresh();
        $this->assertTrue($exam->isPublished());

        $student = $this->createStudent();
        $this->actingAs($student, 'tenant');

        $attempt = $this->createGradedAttempt($exam, $student);

        $response = $this->getJson("/api/student/exams/attempts/{$attempt->id}/result");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $attempt->id);
        $response->assertJsonPath('data.total_score', 10);
    }

    public function test_published_at_appears_in_exam_listing(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'tenant');

        $exam = $this->createExam();

        // Initially not published
        $response = $this->getJson('/api/exams');
        $response->assertStatus(200);
        $examData = collect($response->json('data'))->firstWhere('id', $exam->id);
        $this->assertNotNull($examData);
        $this->assertNull($examData['published_at']);
        $this->assertFalse($examData['is_published']);

        // Publish
        $this->postJson("/api/exams/{$exam->id}/publish-results");

        // Now should appear as published
        $response = $this->getJson('/api/exams');
        $examData = collect($response->json('data'))->firstWhere('id', $exam->id);
        $this->assertNotNull($examData['published_at']);
        $this->assertTrue($examData['is_published']);
    }

    public function test_exam_model_publish_method_guards_double_publish(): void
    {
        $exam = $this->createExam();

        $exam->publish();
        $this->assertNotNull($exam->published_at);
        $this->assertEquals(ExamStatus::Published, $exam->status);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An exam can only be published once it is completed.');
        $exam->publish();
    }

    public function test_exam_model_publish_guards_non_completed_status(): void
    {
        $exam = $this->createExam(['status' => 'draft']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('An exam can only be published once it is completed.');
        $exam->publish();
    }

    public function test_exam_model_can_unpublish(): void
    {
        $exam = $this->createExam();
        $exam->publish();
        $this->assertTrue($exam->isPublished());
        $this->assertEquals(ExamStatus::Published, $exam->status);

        $exam->unpublish();
        $this->assertEquals(ExamStatus::Completed, $exam->status);
        $this->assertNull($exam->published_at);
        $this->assertFalse($exam->isPublished());
    }

    public function test_admin_cannot_publish_non_completed_exam(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'tenant');

        $exam = $this->createExam(['status' => 'draft']);

        $response = $this->postJson("/api/exams/{$exam->id}/publish");

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'An exam can only be published once it is completed.');
    }

    public function test_publish_endpoint_matches_publish_results(): void
    {
        $admin = $this->createAdmin();
        $this->actingAs($admin, 'tenant');

        $exam = $this->createExam();

        $response = $this->postJson("/api/exams/{$exam->id}/publish");

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Exam published.');

        $exam->refresh();
        $this->assertEquals(ExamStatus::Published, $exam->status);
    }
}
