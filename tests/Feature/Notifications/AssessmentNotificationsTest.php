<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Domains\Assessments\Actions\ActivateAssessment;
use App\Domains\Assessments\Actions\OpenAssessment;
use App\Domains\Assessments\Actions\Submissions\ApproveSubmission;
use App\Domains\Assessments\Actions\Submissions\RequestSubmissionChanges;
use App\Domains\Assessments\Actions\Submissions\SubmitSubmissionForReview;
use App\Domains\Assessments\Exceptions\SubmissionCannotBeReviewedException;
use App\Enums\AssessmentStatus;
use App\Enums\RoleType;
use App\Enums\SubmissionQuestionType;
use App\Enums\SubmissionStatus;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\StudentProfile;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Submission;
use App\Models\Tenant\SubmissionQuestion;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use App\Notifications\InAppNotification;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Step 7: every assessment / submission lifecycle event fires an InAppNotification
 * to the right audience, and requestChanges keeps the comment + flip +
 * notification atomic (a guard rejection rolls all three back).
 */
class AssessmentNotificationsTest extends TestCase
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
    public function opening_an_assessment_notifies_eligible_teachers(): void
    {
        Notification::fake();

        app(OpenAssessment::class)->execute($this->makeAssessment());

        Notification::assertSentTo($this->teacher, InAppNotification::class);
    }

    #[Test]
    public function submitting_for_review_notifies_admins(): void
    {
        $submission = $this->submittableSubmission();

        Notification::fake();

        app(SubmitSubmissionForReview::class)->execute($submission);

        Notification::assertSentTo($this->admin, InAppNotification::class);
    }

    #[Test]
    public function requesting_changes_notifies_the_teacher(): void
    {
        $submission = $this->submittableSubmission();
        $submission->submitForReview();

        Notification::fake();

        app(RequestSubmissionChanges::class)->execute($submission, $this->admin, 'Please fix question 2.');

        $this->assertSame(SubmissionStatus::ChangesRequested, $submission->fresh()->status);
        $this->assertDatabaseCount('submission_comments', 1);
        Notification::assertSentTo($this->teacher, InAppNotification::class);
    }

    #[Test]
    public function requesting_changes_rolls_back_atomically_when_the_flip_is_rejected(): void
    {
        // A draft submission cannot be returned; the guard throws after the
        // comment insert, so the transaction rolls the comment back and the
        // teacher notification never fires.
        $submission = $this->submittableSubmission(); // still draft

        Notification::fake();

        try {
            app(RequestSubmissionChanges::class)->execute($submission, $this->admin, 'Nope.');
            $this->fail('Expected SubmissionCannotBeReviewedException.');
        } catch (SubmissionCannotBeReviewedException) {
            // expected
        }

        $this->assertSame(SubmissionStatus::Draft, $submission->fresh()->status);
        $this->assertDatabaseCount('submission_comments', 0);
        Notification::assertNothingSent();
    }

    #[Test]
    public function approving_a_submission_notifies_the_teacher(): void
    {
        $submission = $this->submittableSubmission();
        $submission->submitForReview();

        Notification::fake();

        app(ApproveSubmission::class)->execute($submission, $this->admin);

        Notification::assertSentTo($this->teacher, InAppNotification::class);
    }

    #[Test]
    public function activating_an_assessment_notifies_students(): void
    {
        $assessment = $this->approvedAssessment();
        $assessment->closeSubmissions();

        $student = $this->makeUser('student@test.com', RoleType::Student);
        StudentProfile::create([
            'user_id' => $student->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'admission_number' => 'ADM-001',
            'guardian_email' => 'guardian@test.com',
        ]);

        Notification::fake();

        app(ActivateAssessment::class)->execute($assessment);

        Notification::assertSentTo($student, InAppNotification::class);
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    private function submittableSubmission(): Submission
    {
        $assessment = $this->makeAssessment();
        $assessment->open();

        $submission = $this->makeSubmission([], $assessment);
        $this->addQuestion($submission, marks: 20);

        return $submission->fresh();
    }

    private function approvedAssessment(): Assessment
    {
        $submission = $this->submittableSubmission();
        $submission->submitForReview();
        $submission->approve($this->admin->id);

        return $submission->assessment;
    }

    private function makeAssessment(array $overrides = []): Assessment
    {
        return Assessment::create(array_merge([
            'title' => 'Mid-Term Assessment',
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'created_by' => $this->admin->id,
            'status' => AssessmentStatus::Draft->value,
            'total_marks' => 100,
            'duration_minutes' => 90,
            'submission_closes_at' => now()->addWeek(),
            'student_starts_at' => now()->addWeeks(2),
            'student_ends_at' => now()->addWeeks(2)->addHours(2),
        ], $overrides));
    }

    private function makeSubmission(array $overrides = [], ?Assessment $assessment = null): Submission
    {
        $assessment ??= $this->makeAssessment();

        return Submission::create(array_merge([
            'assessment_id' => $assessment->id,
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
