<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Domains\Assessments\Exceptions\AssessmentCannotBeActivatedException;
use App\Domains\Assessments\Exceptions\AssessmentCannotBeCompletedException;
use App\Domains\Assessments\Exceptions\AssessmentCannotBeOpenedException;
use App\Domains\Assessments\Exceptions\AssessmentStateTransitionException;
use App\Domains\Assessments\Exceptions\SubmissionCannotBeReviewedException;
use App\Domains\Assessments\Exceptions\SubmissionCannotBeSubmittedException;
use App\Domains\Assessments\Exceptions\SubmissionMarksExceedCapException;
use App\Enums\AssessmentStatus;
use App\Enums\RoleType;
use App\Enums\SubmissionQuestionType;
use App\Enums\SubmissionStatus;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Submission;
use App\Models\Tenant\SubmissionQuestion;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssessmentStateMachineTest extends TestCase
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

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole('school_admin');

        $this->teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'One',
            'email' => 'teacher1@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $this->teacher->assignRole('teacher');
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    protected function makeAssessment(array $overrides = []): Assessment
    {
        return Assessment::create(array_merge([
            'title' => 'Mid-Term Assessment',
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'created_by' => $this->admin->id,
            'status' => AssessmentStatus::Draft->value,
            'total_marks' => 100,
        ], $overrides));
    }

    protected function makeSubmission(Assessment $assessment, array $overrides = []): Submission
    {
        return Submission::create(array_merge([
            'assessment_id' => $assessment->id,
            'teacher_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'title' => 'Maths Paper',
            'status' => SubmissionStatus::Draft->value,
            'total_marks' => 0,
        ], $overrides));
    }

    protected function addQuestion(Submission $submission, float $marks = 5, int $order = 1): SubmissionQuestion
    {
        return SubmissionQuestion::create([
            'submission_id' => $submission->id,
            'type' => SubmissionQuestionType::Mcq->value,
            'order' => $order,
            'content' => 'What is 2 + 2?',
            'marks' => $marks,
        ]);
    }

    // ---------------------------------------------------------------------
    // Assessment lifecycle
    // ---------------------------------------------------------------------

    #[Test]
    public function assessment_defaults_to_draft(): void
    {
        $assessment = $this->makeAssessment();

        $this->assertTrue($assessment->isDraft());
        $this->assertSame(AssessmentStatus::Draft, $assessment->status);
    }

    #[Test]
    public function open_requires_a_future_submission_close_time(): void
    {
        $assessment = $this->makeAssessment(['submission_closes_at' => null]);

        $this->expectException(AssessmentCannotBeOpenedException::class);
        $assessment->open();
    }

    #[Test]
    public function open_rejects_a_past_submission_close_time(): void
    {
        $assessment = $this->makeAssessment(['submission_closes_at' => now()->subDay()]);

        $this->expectException(AssessmentCannotBeOpenedException::class);
        $assessment->open();
    }

    #[Test]
    public function open_succeeds_from_draft_with_a_future_close(): void
    {
        $assessment = $this->makeAssessment(['submission_closes_at' => now()->addWeek()]);

        $assessment->open();

        $this->assertTrue($assessment->isOpen());
        $this->assertNotNull($assessment->submission_opens_at);
    }

    #[Test]
    public function close_submissions_only_allowed_from_open(): void
    {
        $assessment = $this->makeAssessment();

        $this->expectException(AssessmentStateTransitionException::class);
        $assessment->closeSubmissions();
    }

    #[Test]
    public function close_submissions_succeeds_from_open(): void
    {
        $assessment = $this->makeAssessment(['submission_closes_at' => now()->addWeek()]);
        $assessment->open();

        $assessment->closeSubmissions();

        $this->assertTrue($assessment->isSubmissionsClosed());
    }

    #[Test]
    public function reopen_only_allowed_from_submissions_closed(): void
    {
        $assessment = $this->makeAssessment();

        $this->expectException(AssessmentCannotBeOpenedException::class);
        $assessment->reopen();
    }

    #[Test]
    public function reopen_returns_a_closed_assessment_to_open(): void
    {
        $assessment = $this->makeAssessment(['submission_closes_at' => now()->addWeek()]);
        $assessment->open();
        $assessment->closeSubmissions();

        $assessment->submission_closes_at = now()->addDays(3);
        $assessment->reopen();

        $this->assertTrue($assessment->isOpen());
    }

    #[Test]
    public function activate_requires_at_least_one_approved_submission(): void
    {
        $assessment = $this->makeAssessment([
            'submission_closes_at' => now()->addWeek(),
            'student_starts_at' => now()->addWeeks(2),
            'student_ends_at' => now()->addWeeks(2)->addHours(2),
        ]);
        $assessment->open();
        $assessment->closeSubmissions();

        // A submission exists but is not approved.
        $this->makeSubmission($assessment, ['status' => SubmissionStatus::Submitted->value]);

        $this->expectException(AssessmentCannotBeActivatedException::class);
        $assessment->activate();
    }

    #[Test]
    public function activate_rejects_an_inverted_student_window(): void
    {
        $assessment = $this->makeAssessment([
            'submission_closes_at' => now()->addWeek(),
            'student_starts_at' => now()->addWeeks(2)->addHours(2),
            'student_ends_at' => now()->addWeeks(2),
        ]);
        $assessment->open();
        $assessment->closeSubmissions();
        $this->makeSubmission($assessment, ['status' => SubmissionStatus::Approved->value]);

        $this->expectException(AssessmentCannotBeActivatedException::class);
        $assessment->activate();
    }

    #[Test]
    public function activate_succeeds_with_an_approved_submission_and_valid_window(): void
    {
        $assessment = $this->makeAssessment([
            'submission_closes_at' => now()->addWeek(),
            'student_starts_at' => now()->addWeeks(2),
            'student_ends_at' => now()->addWeeks(2)->addHours(2),
        ]);
        $assessment->open();
        $assessment->closeSubmissions();
        $this->makeSubmission($assessment, ['status' => SubmissionStatus::Approved->value]);

        $assessment->activate();

        $this->assertTrue($assessment->isActive());
        $this->assertNotNull($assessment->activated_at);
    }

    #[Test]
    public function complete_only_allowed_from_active(): void
    {
        $assessment = $this->makeAssessment();

        $this->expectException(AssessmentCannotBeCompletedException::class);
        $assessment->complete();
    }

    // ---------------------------------------------------------------------
    // Submission lifecycle
    // ---------------------------------------------------------------------

    protected function openAssessment(array $overrides = []): Assessment
    {
        $assessment = $this->makeAssessment(array_merge([
            'submission_closes_at' => now()->addWeek(),
        ], $overrides));
        $assessment->open();

        return $assessment;
    }

    #[Test]
    public function submit_requires_at_least_one_question(): void
    {
        $assessment = $this->openAssessment();
        $submission = $this->makeSubmission($assessment);

        $this->expectException(SubmissionCannotBeSubmittedException::class);
        $submission->submitForReview();
    }

    #[Test]
    public function submit_is_blocked_when_marks_exceed_the_assessment_cap(): void
    {
        $assessment = $this->openAssessment(['total_marks' => 10]);
        $submission = $this->makeSubmission($assessment);
        $this->addQuestion($submission, marks: 8, order: 1);
        $this->addQuestion($submission, marks: 5, order: 2);

        $this->expectException(SubmissionMarksExceedCapException::class);
        $submission->submitForReview();
    }

    #[Test]
    public function submit_is_blocked_when_the_submission_window_is_closed(): void
    {
        $assessment = $this->openAssessment();
        $submission = $this->makeSubmission($assessment);
        $this->addQuestion($submission);

        // Window slams shut after the paper was authored.
        $assessment->closeSubmissions();

        $this->expectException(SubmissionCannotBeSubmittedException::class);
        $submission->submitForReview();
    }

    #[Test]
    public function submit_succeeds_from_draft_with_a_question_within_the_open_window(): void
    {
        $assessment = $this->openAssessment();
        $submission = $this->makeSubmission($assessment);
        $this->addQuestion($submission);

        $submission->submitForReview();

        $this->assertTrue($submission->isSubmitted());
        $this->assertNotNull($submission->submitted_at);
    }

    #[Test]
    public function request_changes_only_allowed_from_submitted(): void
    {
        $assessment = $this->openAssessment();
        $submission = $this->makeSubmission($assessment);

        $this->expectException(SubmissionCannotBeReviewedException::class);
        $submission->requestChanges();
    }

    #[Test]
    public function request_changes_returns_a_submission_to_the_teacher(): void
    {
        $assessment = $this->openAssessment();
        $submission = $this->makeSubmission($assessment);
        $this->addQuestion($submission);
        $submission->submitForReview();

        $submission->requestChanges();

        $this->assertTrue($submission->isChangesRequested());
        $this->assertNotNull($submission->returned_at);
    }

    #[Test]
    public function a_returned_submission_can_be_resubmitted(): void
    {
        $assessment = $this->openAssessment();
        $submission = $this->makeSubmission($assessment);
        $this->addQuestion($submission);
        $submission->submitForReview();
        $submission->requestChanges();

        $submission->submitForReview();

        $this->assertTrue($submission->isSubmitted());
    }

    #[Test]
    public function approve_only_allowed_from_submitted(): void
    {
        $assessment = $this->openAssessment();
        $submission = $this->makeSubmission($assessment);

        $this->expectException(SubmissionCannotBeReviewedException::class);
        $submission->approve($this->admin->id);
    }

    #[Test]
    public function approve_succeeds_from_submitted(): void
    {
        $assessment = $this->openAssessment();
        $submission = $this->makeSubmission($assessment);
        $this->addQuestion($submission);
        $submission->submitForReview();

        $submission->approve($this->admin->id);

        $this->assertTrue($submission->isApproved());
        $this->assertSame($this->admin->id, $submission->approved_by);
        $this->assertNotNull($submission->approved_at);
    }

    #[Test]
    public function one_paper_per_teacher_per_subject_is_enforced(): void
    {
        $assessment = $this->makeAssessment();
        $this->makeSubmission($assessment);

        $this->expectException(QueryException::class);
        $this->makeSubmission($assessment);
    }
}
