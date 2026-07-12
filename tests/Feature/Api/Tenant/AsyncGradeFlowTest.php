<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\QuestionType;
use App\Jobs\GradeExamAttemptJob;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use App\Models\Tenant\QuestionOption;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $suffix = str_replace('-', '_', (string) fake()->uuid());
    $this->tenant = Tenant::factory()->create([
        'id' => 'tenant_'.$suffix,
        'database' => 'tenant_'.$suffix,
    ]);
    tenancy()->initialize($this->tenant);

    $admin = User::create([
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@test.com',
        'password' => bcrypt('password'),
        'role' => 'school_admin',
        'is_active' => true,
    ]);
    $admin->assignRole('school_admin');

    $this->student = User::create([
        'first_name' => 'Student',
        'last_name' => 'One',
        'email' => 'student@test.com',
        'password' => bcrypt('password'),
        'role' => 'student',
        'is_active' => true,
    ]);
    $this->student->assignRole('student');

    $subject = Subject::create(['name' => 'Math', 'code' => 'MATH']);
    $classLevel = ClassLevel::create(['name' => 'Grade 10', 'slug' => 'grade-10']);
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

    Sanctum::actingAs($admin, ['*'], 'tenant');

    $exam = Exam::create([
        'title' => 'Test Exam',
        'subject_id' => $subject->id,
        'class_level_id' => $classLevel->id,
        'term_id' => $term->id,
        'type' => ExamType::Exam->value,
        'duration_minutes' => 60,
        'pass_mark' => 50,
        'total_marks' => 10,
        'max_attempts' => 1,
        'status' => ExamStatus::Active->value,
        'scheduled_start' => now()->subHour(),
        'created_by' => $admin->id,
        'settings' => ['randomize_questions' => false, 'show_result_immediately' => true],
    ]);

    $question = Question::create([
        'content' => 'What is 2+2?',
        'type' => QuestionType::Mcq->value,
        'default_marks' => 10,
        'subject_id' => $subject->id,
        'class_level_id' => $classLevel->id,
        'created_by' => $admin->id,
        'is_active' => true,
        'academic_session_id' => $academicSession->id,
        'term_id' => $term->id,
    ]);

    $correctOption = QuestionOption::create([
        'question_id' => $question->id,
        'content' => '4',
        'is_correct' => true,
        'order' => 1,
    ]);

    QuestionOption::create([
        'question_id' => $question->id,
        'content' => '3',
        'is_correct' => false,
        'order' => 2,
    ]);

    $this->examQuestion = ExamQuestion::create([
        'exam_id' => $exam->id,
        'question_id' => $question->id,
        'order' => 1,
        'marks' => 10,
    ]);

    $this->exam = $exam;
    $this->question = $question;
    $this->correctOption = $correctOption;
});

afterEach(function () {
    try {
        tenancy()->end();
    } catch (\Exception) {
        //
    }

    if (isset($this->tenant)) {
        try {
            $this->tenant->database()->manager()->deleteDatabase($this->tenant);
        } catch (\Exception) {
            //
        }

        try {
            $this->tenant->delete();
        } catch (\Exception) {
            //
        }
    }
});

it('submits an attempt asynchronously', function () {
    Queue::fake();

    Sanctum::actingAs($this->student, ['*'], 'tenant');

    $response = $this->postJson("/api/student/exams/{$this->exam->id}/start");
    $response->assertStatus(201);
    $attemptId = $response->json('data.attempt.id');

    $this->putJson("/api/student/exams/attempts/{$attemptId}/answers/{$this->examQuestion->id}", [
        'selected_option_ids' => [$this->correctOption->id],
    ])->assertStatus(200);

    $response = $this->postJson("/api/student/exams/attempts/{$attemptId}/submit");
    $response->assertStatus(202);
    $response->assertJsonPath('message', 'Exam submitted for grading.');

    $attempt = ExamAttempt::where('id', $attemptId)->first();
    expect($attempt)->not->toBeNull();
    expect($attempt->status)->toBe(ExamAttemptStatus::Submitted->value);

    Queue::assertPushed(GradeExamAttemptJob::class);
});

it('rejects double submission with 409', function () {

    Sanctum::actingAs($this->student, ['*'], 'tenant');

    $response = $this->postJson("/api/student/exams/{$this->exam->id}/start");
    $attemptId = $response->json('data.attempt.id');

    $this->postJson("/api/student/exams/attempts/{$attemptId}/submit")->assertStatus(202);

    $response = $this->postJson("/api/student/exams/attempts/{$attemptId}/submit");
    $response->assertStatus(409);
    $response->assertJsonPath('message', 'Already submitted.');
});

it('rejects submit for non-in-progress attempt', function () {
    Sanctum::actingAs($this->student, ['*'], 'tenant');

    $attempt = new ExamAttempt;
    $attempt->exam_id = $this->exam->id;
    $attempt->student_id = $this->student->id;
    $attempt->attempt_number = 1;
    $attempt->status = ExamAttemptStatus::Graded->value;
    $attempt->started_at = now()->subHour();
    $attempt->submitted_at = now()->subMinutes(30);
    $attempt->save();

    $response = $this->postJson("/api/student/exams/attempts/{$attempt->id}/submit");
    $response->assertStatus(409);
});

it('completes full grading pipeline end-to-end with sync queue', function () {
    Sanctum::actingAs($this->student, ['*'], 'tenant');

    $response = $this->postJson("/api/student/exams/{$this->exam->id}/start");
    $attemptId = $response->json('data.attempt.id');

    $this->putJson("/api/student/exams/attempts/{$attemptId}/answers/{$this->examQuestion->id}", [
        'selected_option_ids' => [$this->correctOption->id],
    ])->assertStatus(200);

    $this->postJson("/api/student/exams/attempts/{$attemptId}/submit")->assertStatus(202);

    $attempt = ExamAttempt::find($attemptId);
    expect($attempt->status)->toBe(ExamAttemptStatus::Graded->value);
    expect((float) $attempt->total_score)->toBe(10.0);
    expect((float) $attempt->percentage_score)->toBe(100.0);
});

it('recovers a Grading attempt on retry', function () {
    $attempt = ExamAttempt::create([
        'exam_id' => $this->exam->id,
        'student_id' => $this->student->id,
        'attempt_number' => 1,
        'status' => ExamAttemptStatus::Grading->value,
        'started_at' => now()->subHour(),
        'submitted_at' => now()->subMinutes(30),
    ]);

    $answer = ExamAnswer::create([
        'attempt_id' => $attempt->id,
        'question_id' => $this->question->id,
        'selected_option_ids' => [$this->correctOption->id],
        'is_correct' => false,
        'marks_awarded' => 0,
    ]);

    GradeExamAttemptJob::dispatchSync(
        $attempt->id,
        (string) tenant('id'),
    );

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamAttemptStatus::Graded->value);
    expect((float) $attempt->total_score)->toBe(10.0);
});

it('returns session state for reconnection', function () {
    Sanctum::actingAs($this->student, ['*'], 'tenant');

    $response = $this->postJson("/api/student/exams/{$this->exam->id}/start");
    $attemptId = $response->json('data.attempt.id');

    $response = $this->getJson("/api/student/exams/attempts/{$attemptId}/session-state");
    $response->assertStatus(200);
    $response->assertJsonPath('data.attempt_id', $attemptId);
});

it('rejects session state from unauthorized user', function () {
    Sanctum::actingAs($this->student, ['*'], 'tenant');

    $response = $this->postJson("/api/student/exams/{$this->exam->id}/start");
    $attemptId = $response->json('data.attempt.id');

    $otherStudent = User::create([
        'first_name' => 'Other',
        'last_name' => 'Student',
        'email' => 'other@test.com',
        'password' => bcrypt('password'),
        'role' => 'student',
        'is_active' => true,
    ]);
    $otherStudent->assignRole('student');

    Sanctum::actingAs($otherStudent, ['*'], 'tenant');

    $response = $this->getJson("/api/student/exams/attempts/{$attemptId}/session-state");
    $response->assertStatus(403);
});
