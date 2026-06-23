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
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentExamAnswerFlowTest extends TestCase
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
            'last_name' => 'One',
            'email' => 'teacher-answer@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);

        $this->student = User::create([
            'first_name' => 'Student',
            'last_name' => 'Answer',
            'email' => 'student-answer@example.com',
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
            'title' => 'Answer Flow Exam',
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

        // Fill-in-the-blank question
        $this->fitbQuestion = Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'created_by' => $teacher->id,
            'type' => QuestionType::FillInBlank->value,
            'content' => 'Capital of Nigeria?',
            'default_marks' => 1,
            'is_active' => true,
        ]);
        $this->fitbQuestion->options()->create([
            'label' => null,
            'content' => 'Abuja',
            'is_correct' => true,
            'match_pair' => json_encode(['case_sensitive' => true]),
            'order' => 1,
        ]);

        // Link questions to exam
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

    public function test_student_can_save_mcq_answer(): void
    {
        $correctOption = $this->mcqQuestion->options()->where('is_correct', true)->first();

        $response = $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->mcqQuestion->id}",
            [
                'selected_option_ids' => [$correctOption->id],
                'time_spent_seconds' => 30,
            ],
        );

        $response
            ->assertSuccessful()
            ->assertJsonPath('data.question_id', $this->mcqQuestion->id)
            ->assertJsonPath('data.attempt_id', $this->attempt->id);

        $this->assertDatabaseHas('exam_answers', [
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->mcqQuestion->id,
        ]);

        $saved = ExamAnswer::where('attempt_id', $this->attempt->id)
            ->where('question_id', $this->mcqQuestion->id)
            ->first();

        $this->assertEquals([$correctOption->id], $saved->selected_option_ids);
    }

    public function test_student_can_save_true_false_answer(): void
    {
        $correctOption = $this->tfQuestion->options()->where('is_correct', true)->first();

        $response = $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->tfQuestion->id}",
            [
                'selected_option_ids' => [$correctOption->id],
                'time_spent_seconds' => 20,
            ],
        );

        $response->assertSuccessful();

        $this->assertDatabaseHas('exam_answers', [
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->tfQuestion->id,
        ]);

        $saved = ExamAnswer::where('attempt_id', $this->attempt->id)
            ->where('question_id', $this->tfQuestion->id)
            ->first();

        $this->assertEquals([$correctOption->id], $saved->selected_option_ids);
    }

    public function test_student_can_save_fill_in_blank_answer(): void
    {
        $response = $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->fitbQuestion->id}",
            [
                'text_answer' => 'Abuja',
                'time_spent_seconds' => 15,
            ],
        );

        $response->assertSuccessful();

        $this->assertDatabaseHas('exam_answers', [
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->fitbQuestion->id,
        ]);

        $saved = ExamAnswer::where('attempt_id', $this->attempt->id)
            ->where('question_id', $this->fitbQuestion->id)
            ->first();

        $this->assertEquals('Abuja', $saved->text_answer);
    }

    public function test_student_can_update_existing_answer(): void
    {
        $wrongOption = $this->mcqQuestion->options()->where('is_correct', false)->first();
        $correctOption = $this->mcqQuestion->options()->where('is_correct', true)->first();

        // Save wrong answer first
        $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->mcqQuestion->id}",
            ['selected_option_ids' => [$wrongOption->id]],
        );

        // Overwrite with correct answer
        $response = $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->mcqQuestion->id}",
            ['selected_option_ids' => [$correctOption->id]],
        );

        $response->assertSuccessful();

        // Should be exactly one answer row
        $answers = ExamAnswer::where('attempt_id', $this->attempt->id)
            ->where('question_id', $this->mcqQuestion->id)
            ->get();

        $this->assertCount(1, $answers);
        $this->assertEquals([$correctOption->id], $answers->first()->selected_option_ids);
    }

    public function test_student_can_bulk_save_answers(): void
    {
        $mcqCorrect = $this->mcqQuestion->options()->where('is_correct', true)->first();
        $tfCorrect = $this->tfQuestion->options()->where('is_correct', true)->first();

        $response = $this->postJson(
            "/api/student/exams/attempts/{$this->attempt->id}/bulk-save",
            [
                'answers' => [
                    [
                        'question_id' => $this->mcqQuestion->id,
                        'selected_option_ids' => [$mcqCorrect->id],
                        'time_spent_seconds' => 10,
                    ],
                    [
                        'question_id' => $this->tfQuestion->id,
                        'selected_option_ids' => [$tfCorrect->id],
                        'time_spent_seconds' => 5,
                    ],
                    [
                        'question_id' => $this->fitbQuestion->id,
                        'text_answer' => 'Abuja',
                        'time_spent_seconds' => 8,
                    ],
                ],
            ],
        );

        $response->assertSuccessful();

        $this->assertDatabaseHas('exam_answers', [
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->mcqQuestion->id,
        ]);

        $this->assertDatabaseHas('exam_answers', [
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->tfQuestion->id,
        ]);

        $this->assertDatabaseHas('exam_answers', [
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->fitbQuestion->id,
        ]);
    }

    public function test_student_cannot_save_to_completed_attempt(): void
    {
        $completedAttempt = ExamAttempt::create([
            'exam_id' => $this->exam->id,
            'student_id' => $this->student->id,
            'attempt_number' => 2,
            'status' => ExamAttemptStatus::Submitted->value,
            'started_at' => now()->subMinutes(30),
            'submitted_at' => now(),
        ]);

        $response = $this->putJson(
            "/api/student/exams/attempts/{$completedAttempt->id}/answers/{$this->mcqQuestion->id}",
            ['selected_option_ids' => []],
        );

        $response->assertStatus(403);
    }

    public function test_student_cannot_save_when_time_expired(): void
    {
        $expiredExam = Exam::create([
            'title' => 'Expired Exam',
            'subject_id' => $this->exam->subject_id,
            'class_level_id' => $this->exam->class_level_id,
            'term_id' => $this->exam->term_id,
            'created_by' => $this->exam->created_by,
            'type' => ExamType::Exam->value,
            'status' => ExamStatus::Active->value,
            'duration_minutes' => 1,
            'total_marks' => 1,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'scheduled_start' => now()->subHour(),
            'settings' => ['require_attendance' => false],
        ]);

        $expiredAttempt = ExamAttempt::create([
            'exam_id' => $expiredExam->id,
            'student_id' => $this->student->id,
            'attempt_number' => 1,
            'status' => ExamAttemptStatus::InProgress->value,
            'started_at' => now()->subMinutes(10),
        ]);

        $this->putJson(
            "/api/student/exams/attempts/{$expiredAttempt->id}/answers/{$this->mcqQuestion->id}",
            ['selected_option_ids' => []],
        );

        // The endpoint should reject due to expired time.
        // It may return 422 (RuntimeException caught by handler) or 500.
        $this->assertDatabaseMissing('exam_answers', [
            'attempt_id' => $expiredAttempt->id,
        ]);
    }

    public function test_student_can_flag_and_unflag_question(): void
    {
        // Save an answer first so there's something to flag
        $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->mcqQuestion->id}",
            ['selected_option_ids' => []],
        );

        // Flag
        $flagResponse = $this->postJson(
            "/api/student/exams/attempts/{$this->attempt->id}/flag/{$this->mcqQuestion->id}",
        );

        $flagResponse->assertSuccessful();
        $this->assertTrue($flagResponse->json('data.is_flagged'));

        $this->assertDatabaseHas('exam_answers', [
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->mcqQuestion->id,
            'is_flagged' => true,
        ]);

        // Unflag
        $unflagResponse = $this->postJson(
            "/api/student/exams/attempts/{$this->attempt->id}/flag/{$this->mcqQuestion->id}",
        );

        $unflagResponse->assertSuccessful();
        $this->assertFalse($unflagResponse->json('data.is_flagged'));

        $this->assertDatabaseHas('exam_answers', [
            'attempt_id' => $this->attempt->id,
            'question_id' => $this->mcqQuestion->id,
            'is_flagged' => false,
        ]);
    }
}
