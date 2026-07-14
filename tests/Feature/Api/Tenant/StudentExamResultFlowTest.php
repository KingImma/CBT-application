<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Domains\Exams\Actions\Attempts\GradeExamAttempt;
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

class StudentExamResultFlowTest extends TestCase
{
    protected Tenant $tenant;

    protected User $student;

    protected Exam $exam;

    protected ExamAttempt $attempt;

    protected Question $mcqQuestion;

    protected Question $fitbQuestion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'tenant']);

        $teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'Result',
            'email' => 'teacher-result@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);

        $this->student = User::create([
            'first_name' => 'Student',
            'last_name' => 'Result',
            'email' => 'student-result@example.com',
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
            'title' => 'Result Flow Exam',
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'term_id' => $term->id,
            'created_by' => $teacher->id,
            'type' => ExamType::Exam->value,
            'status' => ExamStatus::Active->value,
            'duration_minutes' => 60,
            'total_marks' => 2,
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
        ]);

        // FITB question
        $this->fitbQuestion = Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'created_by' => $teacher->id,
            'type' => QuestionType::FillInBlank->value,
            'content' => 'Capital of France?',
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
        foreach ([$this->mcqQuestion, $this->fitbQuestion] as $q) {
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

    private function fullyGradeAttempt(): void
    {
        // Save and submit answers
        $mcqCorrect = $this->mcqQuestion->options()->where('is_correct', true)->first();
        $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->mcqQuestion->id}",
            ['selected_option_ids' => [$mcqCorrect->id]],
        );
        $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->fitbQuestion->id}",
            ['text_answer' => 'Paris'],
        );
        $this->postJson("/api/student/exams/attempts/{$this->attempt->id}/submit");

        $this->attempt->refresh();
        $grader = app(GradeExamAttempt::class);
        $grader->execute($this->attempt->fresh());
        $this->attempt->refresh();

        // Publish results (status + published_at for the results listing query)
        $this->exam->status = ExamStatus::Published;
        $this->exam->published_at = now();
        $this->exam->save();
    }

    public function test_student_can_view_graded_result(): void
    {
        $this->fullyGradeAttempt();

        $response = $this->getJson(
            "/api/student/exams/attempts/{$this->attempt->id}/result",
        );

        $response->assertSuccessful()
            ->assertJsonPath('data.id', $this->attempt->id)
            ->assertJsonPath('data.status', ExamAttemptStatus::Graded->value);
    }

    public function test_result_shows_text_answer_for_fill_in_blank(): void
    {
        $this->fullyGradeAttempt();

        $response = $this->getJson(
            "/api/student/exams/attempts/{$this->attempt->id}/result",
        );

        $response->assertSuccessful();

        // The exam relation loaded via ExamAttemptData includes questions/answers
        // We assert the attempts answers have text_answer via direct DB check
        $fitbAnswer = ExamAnswer::where('attempt_id', $this->attempt->id)
            ->where('question_id', $this->fitbQuestion->id)
            ->first();

        $this->assertEquals('Paris', $fitbAnswer->text_answer);
        $this->assertTrue((bool) $fitbAnswer->is_correct);
    }

    public function test_student_cannot_view_unreleased_result(): void
    {
        // Grade the attempt but do NOT publish the exam
        $mcqCorrect = $this->mcqQuestion->options()->where('is_correct', true)->first();
        $this->putJson(
            "/api/student/exams/attempts/{$this->attempt->id}/answers/{$this->mcqQuestion->id}",
            ['selected_option_ids' => [$mcqCorrect->id]],
        );
        $this->postJson("/api/student/exams/attempts/{$this->attempt->id}/submit");

        $this->attempt->refresh();
        $grader = app(GradeExamAttempt::class);
        $grader->execute($this->attempt->fresh());
        $this->attempt->refresh();

        // Exam is NOT published — result should be forbidden
        $response = $this->getJson(
            "/api/student/exams/attempts/{$this->attempt->id}/result",
        );

        $response->assertStatus(403);
    }

    public function test_student_cannot_view_another_students_result(): void
    {
        $this->fullyGradeAttempt();

        // Act as a different student
        $otherStudent = User::create([
            'first_name' => 'Other',
            'last_name' => 'Student',
            'email' => 'other-result@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $otherStudent->assignRole('student');

        Sanctum::actingAs($otherStudent, ['*'], 'tenant');

        $response = $this->getJson(
            "/api/student/exams/attempts/{$this->attempt->id}/result",
        );

        $response->assertStatus(403);
    }

    public function test_student_can_list_paginated_results(): void
    {
        $this->fullyGradeAttempt();

        $response = $this->getJson('/api/students/results');

        $response->assertSuccessful()
            ->assertJsonPath('data.0.attempt_id', $this->attempt->id)
            ->assertJsonPath('data.0.exam_title', 'Result Flow Exam');

        // Verify questions are included with type-specific grading details
        $response->assertJsonStructure([
            'data' => [
                0 => [
                    'attempt_id',
                    'exam_id',
                    'exam_title',
                    'status',
                    'total_score',
                    'total_marks',
                ],
            ],
        ]);

        // Verify first question (MCQ) has choice-based result fields
        $response->assertJsonStructure([
            'data' => [
                0 => [
                    'questions' => [
                        0 => [
                            'question_id',
                            'type',
                            'marks_available',
                            'marks_awarded',
                            'is_correct',
                            'selected_options',
                            'options',
                        ],
                    ],
                ],
            ],
        ]);

        // Verify second question (FITB) has text-based result fields
        $response->assertJsonStructure([
            'data' => [
                0 => [
                    'questions' => [
                        1 => [
                            'question_id',
                            'type',
                            'marks_available',
                            'marks_awarded',
                            'is_correct',
                            'text_answer',
                            'acceptable_answers',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
