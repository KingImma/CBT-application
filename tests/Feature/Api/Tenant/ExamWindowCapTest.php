<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Domains\Exams\Actions\ActivateExam;
use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\Question;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExamWindowCapTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $teacher;

    protected Subject $subject;

    protected ClassLevel $classLevel;

    protected ClassArm $classArm;

    protected Term $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH101']);
        $this->classLevel = ClassLevel::create(['name' => 'JSS 1', 'slug' => 'jss-1']);
        $this->classArm = ClassArm::create([
            'name' => 'JSS 1 A',
            'class_level_id' => $this->classLevel->id,
        ]);

        $session = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'is_current' => true,
        ]);

        $this->term = Term::create([
            'name' => 'First Term',
            'academic_session_id' => $session->id,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-20',
            'is_current' => true,
        ]);

        $this->admin = $this->makeUser('admin@test.com', RoleType::SchoolAdmin);
        $this->teacher = $this->makeUser('teacher@test.com', RoleType::Teacher);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    #[Test]
    public function activation_keeps_a_preset_slot_window_end(): void
    {
        // Simulates a materialised exam: 3h subject slot, 30m paper.
        $start = now()->addHour();
        $exam = $this->makeSubmittedExam([
            'duration_minutes' => 30,
            'scheduled_start' => $start,
            'window_end' => $start->copy()->addHours(3),
        ]);

        $activated = app(ActivateExam::class)->execute($exam, $this->admin->id);

        $this->assertSame(ExamStatus::Active, $activated->status);
        $this->assertEquals(
            $start->copy()->addHours(3)->toDateTimeString(),
            $activated->window_end->toDateTimeString()
        );
    }

    #[Test]
    public function activation_defaults_window_end_when_unset(): void
    {
        $start = now()->addHour();
        $exam = $this->makeSubmittedExam([
            'duration_minutes' => 30,
            'scheduled_start' => $start,
            'window_end' => null,
        ]);

        $activated = app(ActivateExam::class)->execute($exam, $this->admin->id);

        $this->assertEquals(
            $start->copy()->addMinutes(60)->toDateTimeString(),
            $activated->window_end->toDateTimeString()
        );
    }

    #[Test]
    public function attempt_timer_is_capped_by_the_exam_window(): void
    {
        $exam = $this->makeActiveExam([
            'duration_minutes' => 60,
            'scheduled_start' => now()->subMinutes(50),
            'window_end' => now()->addMinutes(10),
        ]);
        $student = $this->makeUser('capped@test.com', RoleType::Student);

        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => ExamAttemptStatus::InProgress->value,
            'started_at' => now(),
        ]);

        // Personal clock alone would allow ~60m; the window leaves ~10m.
        $this->assertLessThanOrEqual(660, $attempt->getTimeRemainingSeconds());
        $this->assertFalse($attempt->isExpired());

        $this->travelTo(now()->addMinutes(11));

        $this->assertTrue($attempt->fresh()->isExpired());
        $this->assertSame(0, $attempt->fresh()->getTimeRemainingSeconds());
        $this->assertFalse($attempt->fresh()->canSubmit());
    }

    private function makeUser(string $email, RoleType $role): User
    {
        $user = User::create([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role->value,
            'is_active' => true,
        ]);
        $user->assignRole($role->value);

        return $user;
    }

    private function makeSubmittedExam(array $overrides = []): Exam
    {
        $exam = Exam::create(array_merge([
            'title' => 'Window Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => ExamStatus::Draft->value,
            'duration_minutes' => 30,
            'total_marks' => 0,
            'pass_mark' => 5,
            'max_attempts' => 1,
            'scheduled_start' => now()->addHour(),
            'created_by' => $this->teacher->id,
            'settings' => ['require_attendance' => false],
        ], $overrides));

        $question = Question::create([
            'content' => 'What is 2+2?',
            'type' => 'mcq',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
        ]);
        $exam->examQuestions()->create([
            'question_id' => $question->id,
            'order' => 1,
            'marks' => 5.0,
        ]);
        $exam->update(['total_marks' => 5]);
        $exam->submitForReview();

        return $exam->fresh();
    }

    private function makeActiveExam(array $overrides = []): Exam
    {
        $exam = $this->makeSubmittedExam($overrides);
        $exam->update(['status' => ExamStatus::Active->value]);

        return $exam->fresh();
    }
}
