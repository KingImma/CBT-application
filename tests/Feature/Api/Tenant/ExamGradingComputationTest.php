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
use App\Models\Tenant\ExamResult;
use App\Models\Tenant\Question;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamGradingComputationTest extends TestCase
{
    protected Tenant $tenant;

    protected User $student;

    protected User $teacher;

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
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'tenant']);

        $this->teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'Grades',
            'email' => 'teacher-grades@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);

        $this->student = User::create([
            'first_name' => 'Student',
            'last_name' => 'Grades',
            'email' => 'student-grades@example.com',
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
            'title' => 'Grading Computation Exam',
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'term_id' => $term->id,
            'created_by' => $this->teacher->id,
            'type' => ExamType::Exam->value,
            'status' => ExamStatus::Active->value,
            'duration_minutes' => 60,
            'total_marks' => 2,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'scheduled_start' => now()->subHour(),
            'settings' => ['require_attendance' => false],
        ]);

        $this->mcqQuestion = Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'created_by' => $this->teacher->id,
            'type' => QuestionType::Mcq->value,
            'content' => 'What is 2 + 2?',
            'default_marks' => 1,
            'is_active' => true,
        ]);
        $this->mcqQuestion->options()->createMany([
            ['label' => 'A', 'content' => '3', 'is_correct' => false, 'order' => 1],
            ['label' => 'B', 'content' => '4', 'is_correct' => true, 'order' => 2],
        ]);

        $this->fitbQuestion = Question::create([
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'created_by' => $this->teacher->id,
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

    private function gradeAttempt(?array $mcqSelectedOptionIds, ?string $fitbText): void
    {
        if ($mcqSelectedOptionIds !== null) {
            ExamAnswer::create([
                'attempt_id' => $this->attempt->id,
                'question_id' => $this->mcqQuestion->id,
                'selected_option_ids' => $mcqSelectedOptionIds,
                'answered_at' => now(),
            ]);
        }

        if ($fitbText !== null) {
            ExamAnswer::create([
                'attempt_id' => $this->attempt->id,
                'question_id' => $this->fitbQuestion->id,
                'text_answer' => $fitbText,
                'answered_at' => now(),
            ]);
        }

        // Mirror GradeExamAttemptJob: the attempt must be claimed into the
        // Grading state before GradeExamAttempt::canGrade() permits grading.
        $this->attempt->update(['status' => ExamAttemptStatus::Grading->value]);

        app(GradeExamAttempt::class)->execute($this->attempt->fresh());
        $this->attempt->refresh();
    }

    private function marksAwardedFor(Question $question): float
    {
        return (float) ExamAnswer::where('attempt_id', $this->attempt->id)
            ->where('question_id', $question->id)
            ->value('marks_awarded');
    }

    private function isCorrectFor(Question $question): bool
    {
        return (bool) ExamAnswer::where('attempt_id', $this->attempt->id)
            ->where('question_id', $question->id)
            ->value('is_correct');
    }

    public function test_correct_answers_are_awarded_full_marks_and_pass(): void
    {
        $mcqCorrect = $this->mcqQuestion->options()->where('is_correct', true)->first();
        $this->gradeAttempt([$mcqCorrect->id], 'Paris');

        $this->assertSame(ExamAttemptStatus::Graded->value, $this->attempt->status);
        $this->assertEqualsWithDelta(2.0, (float) $this->attempt->total_score, 0.001);
        $this->assertEqualsWithDelta(100.0, (float) $this->attempt->percentage_score, 0.001);

        $result = ExamResult::where('exam_attempt_id', $this->attempt->id)->firstOrFail();
        $this->assertTrue((bool) $result->passed);

        $this->assertEqualsWithDelta(1.0, $this->marksAwardedFor($this->mcqQuestion), 0.001);
        $this->assertTrue($this->isCorrectFor($this->mcqQuestion));
        $this->assertEqualsWithDelta(1.0, $this->marksAwardedFor($this->fitbQuestion), 0.001);
        $this->assertTrue($this->isCorrectFor($this->fitbQuestion));
    }

    public function test_incorrect_answers_award_zero_and_fail(): void
    {
        $mcqWrong = $this->mcqQuestion->options()->where('is_correct', false)->first();
        $this->gradeAttempt([$mcqWrong->id], 'London');

        $this->assertSame(ExamAttemptStatus::Graded->value, $this->attempt->status);
        $this->assertEqualsWithDelta(0.0, (float) $this->attempt->total_score, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $this->attempt->percentage_score, 0.001);

        $result = ExamResult::where('exam_attempt_id', $this->attempt->id)->firstOrFail();
        $this->assertFalse((bool) $result->passed);

        $this->assertEqualsWithDelta(0.0, $this->marksAwardedFor($this->mcqQuestion), 0.001);
        $this->assertFalse($this->isCorrectFor($this->mcqQuestion));
        $this->assertEqualsWithDelta(0.0, $this->marksAwardedFor($this->fitbQuestion), 0.001);
        $this->assertFalse($this->isCorrectFor($this->fitbQuestion));
    }
}
