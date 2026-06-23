<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Actions\Tenants\Exam\ManageExamSession;
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

class StudentExamSubmitFlowTest extends TestCase
{
    protected Tenant $tenant;

    protected User $student;

    protected Exam $exam;

    protected ExamAttempt $attempt;

    protected Question $mcqQuestion;

    protected Question $tfQuestion;

    protected Question $fitbQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'tenant']);

        $teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'Submit',
            'email' => 'teacher-submit@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);

        $this->student = User::create([
            'first_name' => 'Student',
            'last_name' => 'Submit',
            'email' => 'student-submit@example.com',
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
            'title' => 'Submit Flow Exam',
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'term_id' => $term->id,
            'created_by' => $teacher->id,
            'type' => ExamType::Exam->value,
            'status' => ExamStatus::Active->value,
            'duration_minutes' => 60,
            'total_marks' => 3,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'scheduled_start' => now()->subHour(),
            'settings' => ['require_attendance' => false],
        ]);

        // MCQ question
        $this->mcqQuestion = Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'created_by' => $teacher->id,
            'type' => QuestionType::Mcq->value,
            'content' => 'What is 2 + 2?',
            'default_marks' => 1,
            'is_active' => true,
        ]);
        $this->mcqQuestion->options()->createMany([
            ['label' => 'A', 'content' => '3', 'is_correct' => false, 'order' => 1],
            ['label' => 'B', 'content' => '4', 'is_correct' => true, 'order' => 2],
            ['label' => 'C', 'content' => '5', 'is_correct' => false, 'order' => 3],
        ]);

        // True/False question
        $this->tfQuestion = Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'created_by' => $teacher->id,
            'type' => QuestionType::TrueFalse->value,
            'content' => 'The sky is green.',
            'default_marks' => 1,
            'is_active' => true,
        ]);
        $this->tfQuestion->options()->createMany([
            ['label' => 'True', 'content' => 'True', 'is_correct' => false, 'order' => 1],
            ['label' => 'False', 'content' => 'False', 'is_correct' => true, 'order' => 2],
        ]);

        // Fill-in-the-blank question with case-insensitive accepted answer
        $this->fitbQuestion = Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'created_by' => $teacher->id,
            'type' => QuestionType::FillInBlank->value,
            'content' => 'What is the capital of France?',
            'default_marks' => 1,
            'is_active' => true,
        ]);
        $this->fitbQuestion->options()->create([
            'label' => null,
            'content' => 'Paris',
            'is_correct' => true,
            'match_pair' => json_encode(['case_sensitive' => false]),
            'order' => 1,
        ]);

        $order = 1;
        foreach ([$this->mcqQuestion, $this->tfQuestion, $this->fitbQuestion] as $q) {
            ExamQuestion::create([
                'exam_id' => $this->exam->id,
                'question_id' => $q->id,
                'order' => $order++,
                'marks' => 1,
            ]);
        }

        $this->attempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => ExamAttemptStatus::InProgress->value,
            'started_at' => now(),
        ]);

        Sanctum::actingAs($this->student, ['*'], 'tenant');
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();

        parent::tearDown();
    }

    public function test_student_can_submit_attempt(): void
    {
        $response = $this->postJson(
            "/api/student/exams/attempts/{$this->attempt->id}/submit",
        );

        $response->assertStatus(202)
            ->assertJsonPath('data.attempt_id', $this->attempt->id);

        $this->attempt->refresh();
        $this->assertEquals(ExamAttemptStatus::Submitted->value, $this->attempt->status);
    }

    public function test_student_cannot_submit_already_submitted(): void
    {
        $this->postJson("/api/student/exams/attempts/{$this->attempt->id}/submit");

        $response = $this->postJson(
            "/api/student/exams/attempts/{$this->attempt->id}/submit",
        );

        $response->assertStatus(409);
    }

    public function test_mcq_answer_is_graded_correctly(): void
    {
        $correctOption = $this->mcqQuestion->options()->where('is_correct', true)->first();

        // Save correct answer
        $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->mcqQuestion->id}",
            ['selected_option_ids' => [$correctOption->id]],
        );

        // Submit
        $this->postJson("/api/student/exams/attempts/{$this->attempt->id}/submit");

        // Grade manually (the job is queued to Redis, so run inline)
        $this->attempt->refresh();
        $sessionManager = app(ManageExamSession::class);
        $sessionManager->gradeAttempt($this->attempt->fresh(), $this->exam);

        $this->attempt->refresh();
        $this->assertEquals(ExamAttemptStatus::Graded->value, $this->attempt->status);
        $this->assertEquals(1.0, (float) $this->attempt->total_score);

        $answer = $this->attempt->answers()->where('question_id', $this->mcqQuestion->id)->first();
        $this->assertTrue((bool) $answer->is_correct);
        $this->assertEquals(1.0, (float) $answer->marks_awarded);
    }

    public function test_true_false_answer_is_graded_correctly(): void
    {
        $correctOption = $this->tfQuestion->options()->where('is_correct', true)->first();

        $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->tfQuestion->id}",
            ['selected_option_ids' => [$correctOption->id]],
        );

        $this->postJson("/api/student/exams/attempts/{$this->attempt->id}/submit");

        $this->attempt->refresh();
        $sessionManager = app(ManageExamSession::class);
        $sessionManager->gradeAttempt($this->attempt->fresh(), $this->exam);

        $this->attempt->refresh();
        $this->assertEquals(1.0, (float) $this->attempt->total_score);

        $answer = $this->attempt->answers()->where('question_id', $this->tfQuestion->id)->first();
        $this->assertTrue((bool) $answer->is_correct);
    }

    public function test_fill_in_blank_answer_is_graded_case_insensitively(): void
    {
        $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->fitbQuestion->id}",
            ['text_answer' => ' paris '],  // Extra spaces + wrong case
        );

        $this->postJson("/api/student/exams/attempts/{$this->attempt->id}/submit");

        $this->attempt->refresh();
        $sessionManager = app(ManageExamSession::class);
        $sessionManager->gradeAttempt($this->attempt->fresh(), $this->exam);

        $this->attempt->refresh();
        $this->assertEquals(1.0, (float) $this->attempt->total_score);

        $answer = $this->attempt->answers()->where('question_id', $this->fitbQuestion->id)->first();
        $this->assertTrue((bool) $answer->is_correct);
    }
}
