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
use App\Models\Tenant\QuestionOption;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StudentResultsRouteTest extends TestCase
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
        tenancy()->end();
        $this->tenant->delete();

        parent::tearDown();
    }

    #[Test]
    public function student_results_route_lists_published_results_and_rejects_bad_exam_filters(): void
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

        $teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'One',
            'email' => 'teacher@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $teacher->assignRole('teacher');

        $student = User::create([
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => 'student@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $student->assignRole('student');

        $exam = Exam::create([
            'title' => 'Mathematics Exam',
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'term_id' => $term->id,
            'created_by' => $teacher->id,
            'type' => ExamType::Exam->value,
            'status' => ExamStatus::Published->value,
            'duration_minutes' => 60,
            'total_marks' => 10,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'published_at' => now(),
            'settings' => ['require_attendance' => false],
        ]);

        $question = Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'created_by' => $teacher->id,
            'type' => QuestionType::McqSingle->value,
            'content' => 'What is 2 + 2?',
            'default_marks' => 10,
            'is_active' => true,
            'academic_session_id' => $academicSession->id,
            'term_id' => $term->id,
        ]);

        $option = QuestionOption::create([
            'question_id' => $question->id,
            'content' => '4',
            'is_correct' => true,
            'order' => 1,
        ]);

        ExamQuestion::create([
            'exam_id' => $exam->id,
            'question_id' => $question->id,
            'order' => 1,
            'marks' => 10,
        ]);

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => ExamAttemptStatus::Graded->value,
            'started_at' => now()->subHour(),
            'submitted_at' => now()->subMinutes(30),
            'total_score' => 10,
            'percentage_score' => 100,
            'time_spent_seconds' => 1800,
        ]);

        $attempt->answers()->create([
            'question_id' => $question->id,
            'selected_option_ids' => [$option->id],
            'is_correct' => true,
            'marks_awarded' => 10,
        ]);

        Sanctum::actingAs($student, ['*'], 'tenant');

        $listingResponse = $this->getJson('/api/students/results');

        $listingResponse
            ->assertSuccessful()
            ->assertJsonPath('data.0.id', $attempt->id)
            ->assertJsonPath('data.0.exam.id', $exam->id);

        $this->getJson('/api/students/results?exam_id=results')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['exam_id']);

        $this->getJson('/api/student/exams/results')
            ->assertNotFound();
    }
}
