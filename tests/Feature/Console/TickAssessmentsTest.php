<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\AssessmentStatus;
use App\Enums\ExamAttemptStatus;
use App\Enums\QuestionSubmissionStatus;
use App\Enums\RoleType;
use App\Enums\SubmissionQuestionType;
use App\Enums\SubmissionStatus;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ScheduleSubject;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Submission;
use App\Models\Tenant\SubmissionQuestion;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The assessments:tick clock over AssessmentSchedule occurrences:
 * expired question windows close, closed schedules with approved papers
 * activate (materialising exams), finished schedules complete and
 * force-submit open attempts.
 */
class TickAssessmentsTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $teacher;

    protected Subject $subject;

    protected ClassLevel $classLevel;

    protected ClassArm $classArm;

    protected AcademicSession $academicSession;

    protected Term $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH101']);
        $this->classLevel = ClassLevel::create(['name' => 'Grade 10', 'slug' => 'grade-10']);
        $this->classArm = ClassArm::create([
            'name' => 'Grade 10 A',
            'class_level_id' => $this->classLevel->id,
        ]);

        $this->academicSession = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'is_current' => true,
        ]);

        $this->term = Term::create([
            'name' => 'First Term',
            'academic_session_id' => $this->academicSession->id,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-20',
            'is_current' => true,
        ]);

        $this->admin = $this->makeUser('admin@test.com', RoleType::SchoolAdmin);
        $this->teacher = $this->makeUser('teacher@test.com', RoleType::Teacher);
        $this->assignSubject($this->teacher);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    #[Test]
    public function tick_closes_expired_submission_windows(): void
    {
        $schedule = $this->makeSchedule();

        $this->travelTo(now()->addWeek()->addMinute());
        $this->runTick();

        $this->assertSame(QuestionSubmissionStatus::Closed, $schedule->fresh()->question_submission_status);
        $this->assertSame(AssessmentStatus::Draft, $schedule->fresh()->assessment_status);
    }

    #[Test]
    public function tick_leaves_future_windows_open(): void
    {
        // question_submission_ends is NOT NULL at the DB level, so every
        // schedule carries a deadline; a future one must survive the tick.
        $schedule = $this->makeSchedule();

        $this->runTick();

        $this->assertSame(QuestionSubmissionStatus::Open, $schedule->fresh()->question_submission_status);
        $this->assertSame(AssessmentStatus::Draft, $schedule->fresh()->assessment_status);
    }

    #[Test]
    public function tick_activates_scheduled_assessments_and_materialises_exams(): void
    {
        $schedule = $this->approvedSchedule();
        $schedule->closeSubmissions();

        $this->travelTo(now()->addWeeks(2)->addMinute());
        $this->runTick();

        $schedule->refresh();
        $this->assertSame(AssessmentStatus::Active, $schedule->assessment_status);

        $submission = $schedule->submissions()->first();
        $this->assertNotNull($submission->exam_id, 'activation should materialise an exam');
        $this->assertDatabaseCount('exams', 1);
    }

    #[Test]
    public function tick_skips_activation_when_no_approved_submissions(): void
    {
        // A closed window with only a draft submission cannot activate.
        $schedule = $this->makeSchedule();
        $this->makeSubmission($schedule); // draft, never approved
        $this->makeSlot($schedule);
        $schedule->closeSubmissions();

        $this->travelTo(now()->addWeeks(2)->addMinute());
        $this->runTick();

        $this->assertSame(AssessmentStatus::Draft, $schedule->fresh()->assessment_status);
        $this->assertDatabaseCount('exams', 0);
    }

    #[Test]
    public function tick_completes_finished_assessments_and_force_submits_open_attempts(): void
    {
        Event::fake();

        // Drive the schedule all the way to active with a materialised exam.
        $schedule = $this->approvedSchedule();
        $schedule->closeSubmissions();

        $this->travelTo(now()->addWeeks(2)->addMinute());
        $this->runTick();

        $schedule->refresh();
        $this->assertSame(AssessmentStatus::Active, $schedule->assessment_status);

        $exam = $schedule->submissions()->first()->exam;
        $exam->update(['expected_attempts' => 1, 'completed_attempts' => 0]);

        $student = $this->makeUser('student@test.com', RoleType::Student);
        $attempt = ExamAttempt::create([
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'attempt_number' => 1,
            'status' => ExamAttemptStatus::InProgress->value,
            'started_at' => now(),
        ]);

        // Move past the student window end and tick again.
        $this->travelTo($schedule->assessment_ends->copy()->addMinute());
        $this->runTick();

        $this->assertSame(AssessmentStatus::Completed, $schedule->fresh()->assessment_status);
        $this->assertSame(ExamAttemptStatus::Timed_out->value, $attempt->fresh()->status);
        $this->assertSame(1, $exam->fresh()->completed_attempts);
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    /**
     * Runs the command, then re-initialises tenancy: Tenant::run() reverts to
     * central context on exit, so post-run assertions need the tenant DB back.
     */
    private function runTick(): void
    {
        $this->artisan('assessments:tick')->assertSuccessful();
        tenancy()->initialize($this->tenant);
    }

    private function approvedSchedule(): AssessmentSchedule
    {
        $schedule = $this->makeSchedule();

        $submission = $this->makeSubmission($schedule);
        $this->addQuestion($submission, marks: 20);
        $submission->submitForReview();
        $submission->approve($this->admin->id);
        $this->makeSlot($schedule);

        return $schedule;
    }

    private function makeAssessment(array $overrides = []): Assessment
    {
        return Assessment::create(array_merge([
            'title' => 'End of Term Exam',
            'created_by' => $this->admin->id,
            'total_marks' => 100,
            'duration_minutes' => 90,
            'description' => 'Covers weeks one to six.',
        ], $overrides));
    }

    private function makeSchedule(?Assessment $assessment = null, array $overrides = []): AssessmentSchedule
    {
        return AssessmentSchedule::create(array_merge([
            'assessment_id' => ($assessment ?? $this->makeAssessment())->id,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => null,
            'question_submission_ends' => now()->addWeek(),
            'assessment_starts' => now()->addWeeks(2),
            'assessment_ends' => now()->addWeeks(2)->addHours(2),
        ], $overrides));
    }

    private function makeSlot(AssessmentSchedule $schedule, ?Subject $subject = null, array $overrides = []): ScheduleSubject
    {
        return ScheduleSubject::create(array_merge([
            'assessment_schedule_id' => $schedule->id,
            'subject_id' => ($subject ?? $this->subject)->id,
            'starts_at' => $schedule->assessment_starts,
            'ends_at' => $schedule->assessment_ends,
        ], $overrides));
    }

    private function makeSubmission(?AssessmentSchedule $schedule = null, array $overrides = []): Submission
    {
        return Submission::create(array_merge([
            'assessment_schedule_id' => ($schedule ?? $this->makeSchedule())->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'title' => 'Maths Paper 1',
            'status' => SubmissionStatus::Draft->value,
            'total_marks' => 0,
        ], $overrides));
    }

    private function addQuestion(Submission $submission, float $marks = 5): SubmissionQuestion
    {
        $question = $submission->submissionQuestions()->create([
            'type' => SubmissionQuestionType::Mcq->value,
            'order' => (int) $submission->submissionQuestions()->max('order') + 1,
            'content' => 'What is 2 + 2?',
            'marks' => $marks,
        ]);

        $question->options()->createMany([
            ['label' => 'A', 'content' => '3', 'is_correct' => false, 'order' => 1],
            ['label' => 'B', 'content' => '4', 'is_correct' => true, 'order' => 2],
        ]);

        $submission->update(['total_marks' => (float) $submission->submissionQuestions()->sum('marks')]);

        return $question;
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

    private function assignSubject(User $teacher, ?Subject $subject = null): TeacherSubjectAssignment
    {
        return TeacherSubjectAssignment::create([
            'user_id' => $teacher->id,
            'subject_id' => ($subject ?? $this->subject)->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->academicSession->id,
        ]);
    }
}
