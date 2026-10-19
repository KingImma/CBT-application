<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\RoleType;
use App\Enums\SubmissionStatus;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Submission;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The ExamCompleted chain only fires going forward: exams that were already
 * completed (or published) before it shipped leave their materialised
 * submission on `approved`, which now blocks schedule publishing. The
 * backfill marks those submissions `completed` idempotently.
 */
class BackfillSubmissionCompletionTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $teacher;

    protected Subject $subject;

    protected ClassLevel $classLevel;

    protected AcademicSession $academicSession;

    protected Term $term;

    protected AssessmentSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH101']);
        $this->classLevel = ClassLevel::create(['name' => 'Grade 10', 'slug' => 'grade-10']);
        ClassArm::create([
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

        $this->schedule = AssessmentSchedule::create([
            'assessment_id' => $this->makeAssessment()->id,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => null,
            'question_submission_ends' => now()->addWeek(),
            'assessment_starts' => now()->addWeeks(2),
            'assessment_ends' => now()->addWeeks(2)->addHours(2),
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    /**
     * The command initializes tenancy itself and ends it in its finally
     * block (which unregisters the dynamic tenant connection), so park the
     * current context first and restore it after, then return exit + output.
     *
     * @return array{0:int,1:string} exit code + output
     */
    private function runCommand(?string $tenantId = null): array
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $exit = Artisan::call('assessments:mark-submissions-completed', $tenantId ? ['--tenant' => $tenantId] : []);
        $output = Artisan::output();

        tenancy()->initialize($this->tenant);

        return [$exit, $output];
    }

    protected function makeUser(string $email, RoleType $role): User
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

    protected function makeAssessment(array $overrides = []): Assessment
    {
        return Assessment::create(array_merge([
            'title' => 'End of Term Exam',
            'created_by' => $this->admin->id,
            'total_marks' => 100,
            'duration_minutes' => 90,
            'description' => 'Covers weeks one to six.',
        ], $overrides));
    }

    protected function makeExam(ExamStatus $status): Exam
    {
        return Exam::create([
            'title' => 'Legacy Maths Paper',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => null,
            'term_id' => $this->term->id,
            'created_by' => $this->admin->id,
            'type' => ExamType::Exam->value,
            'status' => $status->value,
            'duration_minutes' => 90,
            'total_marks' => 100,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'window_end' => now()->subDay(),
            'expected_attempts' => 1,
            'completed_attempts' => 1,
            'settings' => [],
        ]);
    }

    /**
     * A pre-chain state: the exam is finished but the submission is still
     * `approved` (rows inserted directly, so no ExamCompleted event fires).
     */
    protected function makeLegacySubmission(Exam $exam): Submission
    {
        return Submission::create([
            'assessment_schedule_id' => $this->schedule->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'title' => 'Maths Paper 1',
            'status' => SubmissionStatus::Approved->value,
            'approved_at' => now(),
            'approved_by' => $this->admin->id,
            'total_marks' => 100,
            'exam_id' => $exam->id,
        ]);
    }

    #[Test]
    public function it_marks_approved_submissions_completed_for_already_completed_exams(): void
    {
        $submission = $this->makeLegacySubmission($this->makeExam(ExamStatus::Completed));
        $this->assertSame(SubmissionStatus::Approved, $submission->fresh()->status);

        [$exit, $output] = $this->runCommand($this->tenant->id);

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('Checking 1 tenant(s)...', $output);
        $this->assertSame(SubmissionStatus::Completed, $submission->fresh()->status);
    }

    #[Test]
    public function it_also_covers_exams_that_were_already_published(): void
    {
        $submission = $this->makeLegacySubmission($this->makeExam(ExamStatus::Published));
        $this->assertSame(SubmissionStatus::Approved, $submission->fresh()->status);

        [$exit, $output] = $this->runCommand($this->tenant->id);

        $this->assertSame(0, $exit, $output);
        $this->assertSame(SubmissionStatus::Completed, $submission->fresh()->status);
    }

    #[Test]
    public function it_leaves_submissions_alone_while_their_exam_is_still_active(): void
    {
        $submission = $this->makeLegacySubmission($this->makeExam(ExamStatus::Active));

        [$exit, $output] = $this->runCommand($this->tenant->id);

        $this->assertSame(0, $exit, $output);
        $this->assertSame(SubmissionStatus::Approved, $submission->fresh()->status);
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $submission = $this->makeLegacySubmission($this->makeExam(ExamStatus::Completed));

        [$exit, $output] = $this->runCommand($this->tenant->id);
        $this->assertSame(0, $exit, $output);
        $this->assertSame(SubmissionStatus::Completed, $submission->fresh()->status);

        $this->runCommand($this->tenant->id);
        $this->assertSame(SubmissionStatus::Completed, $submission->fresh()->status);
    }

    #[Test]
    public function it_can_be_scoped_to_a_single_tenant(): void
    {
        $submission = $this->makeLegacySubmission($this->makeExam(ExamStatus::Completed));

        [$exit, $output] = $this->runCommand($this->tenant->id);

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('Checking 1 tenant(s)...', $output);
        $this->assertSame(SubmissionStatus::Completed, $submission->fresh()->status);
    }

    #[Test]
    public function a_legacy_constraint_name_does_not_block_the_corrective_migration(): void
    {
        // Production constraints predate the `submissions` -> `teacher_submissions`
        // rename, so the status check is `submissions_status_check`; the earlier
        // enum-swap migrations could not replace it and `completed` was rejected
        // (SQLSTATE[23514]). Reproduce that state by renaming the constraint.
        DB::statement('ALTER TABLE teacher_submissions DROP CONSTRAINT IF EXISTS teacher_submissions_status_check');
        DB::statement("ALTER TABLE teacher_submissions ADD CONSTRAINT submissions_status_check CHECK (status IN ('draft', 'submitted', 'changes_requested', 'approved'))");

        $submission = $this->makeLegacySubmission($this->makeExam(ExamStatus::Completed));

        $migration = require database_path('migrations/tenant/2026_08_27_000004_normalize_status_constraints_and_backfill.php');
        $migration->up();

        $this->assertSame(SubmissionStatus::Completed, $submission->fresh()->status);

        $constraints = DB::select(
            "SELECT c.conname FROM pg_constraint c WHERE c.conrelid = 'teacher_submissions'::regclass AND c.contype = 'c'"
        );
        $this->assertCount(1, $constraints);
        $this->assertSame('teacher_submissions_status_check', $constraints[0]->conname);

        $definition = DB::selectOne(
            "SELECT pg_get_constraintdef(oid) AS def FROM pg_constraint WHERE conrelid = 'teacher_submissions'::regclass AND contype = 'c'"
        );
        $this->assertStringContainsString('completed', $definition->def);
    }
}
