<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Domains\Assessments\Actions\ActivateAssessment;
use App\Domains\Exams\Actions\ForceCompleteExam;
use App\Domains\Exams\Actions\PublishExamResults;
use App\Enums\AssessmentStatus;
use App\Enums\ExamStatus;
use App\Enums\RoleType;
use App\Enums\SubmissionQuestionType;
use App\Enums\SubmissionStatus;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\Assessment;
use App\Models\Tenant\AssessmentSchedule;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ScheduleSubject;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Submission;
use App\Models\Tenant\SubmissionQuestion;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bulk publish (`POST assessment-schedules/{schedule}/publish-results`) is
 * all-or-nothing: the schedule only moves to `published` once every
 * materialised submission's exam is completed, and a repeat request is
 * answered with an informational message rather than an error. Completing an
 * exam (force or time-based) chains a `completed` submission status via the
 * ExamCompleted event — the exam is internal, the submission is the gate.
 */
class AssessmentSchedulePublishTest extends TestCase
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

    protected function assignSubject(User $teacher, ?Subject $subject = null): TeacherSubjectAssignment
    {
        return TeacherSubjectAssignment::create([
            'user_id' => $teacher->id,
            'subject_id' => ($subject ?? $this->subject)->id,
            'class_level_id' => $this->classLevel->id,
            'academic_session_id' => $this->academicSession->id,
        ]);
    }

    protected function makeSchedule(?Assessment $assessment = null, array $overrides = []): AssessmentSchedule
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

    protected function makeSlot(AssessmentSchedule $schedule, ?Subject $subject = null, array $overrides = []): ScheduleSubject
    {
        return ScheduleSubject::create(array_merge([
            'assessment_schedule_id' => $schedule->id,
            'subject_id' => ($subject ?? $this->subject)->id,
            'starts_at' => $schedule->assessment_starts,
            'ends_at' => $schedule->assessment_ends,
        ], $overrides));
    }

    protected function makeSubmission(?AssessmentSchedule $schedule = null, array $overrides = []): Submission
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

    protected function addQuestion(Submission $submission, float $marks = 5): SubmissionQuestion
    {
        $question = $submission->submissionQuestions()->create([
            'type' => SubmissionQuestionType::Mcq->value,
            'order' => (int) $submission->submissionQuestions()->max('order') + 1,
            'content' => 'What is 2 + 2?',
            'marks' => $marks,
        ]);

        $submission->update(['total_marks' => (float) $submission->submissionQuestions()->sum('marks')]);

        return $question;
    }

    /**
     * A schedule with one approved paper walked through activation, so an exam
     * is materialised; the exam is left in its freshly-materialised draft state.
     *
     * @return array{0: AssessmentSchedule, 1: Exam}
     */
    protected function makeActivatedSchedule(): array
    {
        $schedule = $this->makeSchedule();
        $submission = $this->makeSubmission($schedule);
        $this->addQuestion($submission, marks: 20);
        $submission->submitForReview();
        $submission->approve($this->admin->id);
        $this->makeSlot($schedule);
        $schedule->closeSubmissions();

        app(ActivateAssessment::class)->execute($schedule);

        $submission->refresh();
        $exam = Exam::findOrFail($submission->exam_id);

        return [$schedule, $exam];
    }

    // ---------------------------------------------------------------------
    // Bulk publish
    // ---------------------------------------------------------------------

    #[Test]
    public function an_admin_can_bulk_publish_a_schedule_once_all_exams_are_completed(): void
    {
        [$schedule, $exam] = $this->makeActivatedSchedule();
        $submission = $schedule->submissions()->whereNotNull('exam_id')->firstOrFail();
        $exam->update(['status' => ExamStatus::Completed->value]);

        $this->assertSame(SubmissionStatus::Completed, $submission->fresh()->status);

        $this->actingAs($this->admin, 'tenant');

        $response = $this->postJson("/api/assessment-schedules/{$schedule->id}/publish-results");

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', '1 exam(s) published.');

        $schedule->refresh();
        $this->assertTrue($schedule->isPublished());
        $this->assertSame(AssessmentStatus::Published, $schedule->assessment_status);
        $this->assertNotNull($schedule->published_at);

        $exam->refresh();
        $this->assertSame(ExamStatus::Published, $exam->status);
        $this->assertNotNull($exam->published_at);
    }

    #[Test]
    public function bulk_publish_is_rejected_until_every_exam_is_completed(): void
    {
        [$schedule, $exam] = $this->makeActivatedSchedule();
        $submission = $schedule->submissions()->whereNotNull('exam_id')->firstOrFail();
        $this->assertSame(ExamStatus::Active, $exam->status);
        $this->assertSame(SubmissionStatus::Approved, $submission->status);

        $this->actingAs($this->admin, 'tenant');

        $response = $this->postJson("/api/assessment-schedules/{$schedule->id}/publish-results");

        $response->assertConflict();
        $this->assertStringContainsString('must be completed', strtolower($response->json('message')));

        $schedule->refresh();
        $this->assertFalse($schedule->isPublished());
        $this->assertNull($schedule->published_at);
        $this->assertSame(ExamStatus::Active, $exam->fresh()->status);
    }

    #[Test]
    public function a_repeated_publish_request_is_informed_that_results_are_already_published(): void
    {
        [$schedule, $exam] = $this->makeActivatedSchedule();
        $exam->update(['status' => ExamStatus::Completed->value]);

        $this->actingAs($this->admin, 'tenant');

        $this->postJson("/api/assessment-schedules/{$schedule->id}/publish-results")->assertSuccessful();

        $publishedAt = $schedule->fresh()->published_at;

        $response = $this->postJson("/api/assessment-schedules/{$schedule->id}/publish-results");

        $response->assertSuccessful();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Results already published.');
        $response->assertJsonPath('data.results', []);

        $this->assertSame(ExamStatus::Published, $exam->fresh()->status);
        $this->assertTrue($schedule->fresh()->published_at->equalTo($publishedAt));
    }

    #[Test]
    public function a_teacher_cannot_bulk_publish_a_schedule(): void
    {
        [$schedule, $exam] = $this->makeActivatedSchedule();
        $exam->update(['status' => ExamStatus::Completed->value]);

        $this->actingAs($this->teacher, 'tenant');

        $this->postJson("/api/assessment-schedules/{$schedule->id}/publish-results")->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Individually-published exam + completion chain
    // ---------------------------------------------------------------------

    /**
     * @return array{0: AssessmentSchedule, 1: Exam, 2: Exam}
     */
    protected function makeTwoExamSchedule(): array
    {
        $schedule = $this->makeSchedule();

        $first = $this->makeSubmission($schedule);
        $this->addQuestion($first, marks: 20);
        $first->submitForReview();
        $first->approve($this->admin->id);

        $secondTeacher = $this->makeUser('second@test.com', RoleType::Teacher);
        $physics = Subject::create(['name' => 'Physics', 'code' => 'PHY101']);
        $this->assignSubject($secondTeacher, $physics);
        $second = $this->makeSubmission($schedule, ['teacher_id' => $secondTeacher->id, 'subject_id' => $physics->id]);
        $this->addQuestion($second, marks: 20);
        $second->submitForReview();
        $second->approve($this->admin->id);

        $this->makeSlot($schedule);
        $this->makeSlot($schedule, $physics);
        $schedule->closeSubmissions();
        app(ActivateAssessment::class)->execute($schedule);

        $first->refresh();
        $second->refresh();

        return [$schedule, Exam::findOrFail($first->exam_id), Exam::findOrFail($second->exam_id)];
    }

    #[Test]
    public function an_individually_published_exam_is_skipped_during_bulk_publish(): void
    {
        [$schedule, $firstExam, $secondExam] = $this->makeTwoExamSchedule();

        $firstExam->update(['status' => ExamStatus::Completed->value]);
        app(PublishExamResults::class)->execute($firstExam->fresh());

        $secondExam->update(['status' => ExamStatus::Completed->value]);

        $this->actingAs($this->admin, 'tenant');

        $response = $this->postJson("/api/assessment-schedules/{$schedule->id}/publish-results");

        $response->assertSuccessful();
        $response->assertJsonPath('message', '1 exam(s) published, 1 skipped.');

        $schedule->refresh();
        $this->assertTrue($schedule->isPublished());
        $this->assertNotNull($schedule->published_at);
        $this->assertSame(ExamStatus::Published, $firstExam->fresh()->status);
        $this->assertSame(ExamStatus::Published, $secondExam->fresh()->status);
    }

    #[Test]
    public function force_completing_an_exam_marks_its_submission_completed(): void
    {
        [$schedule, $exam] = $this->makeActivatedSchedule();
        $submission = $schedule->submissions()->whereNotNull('exam_id')->firstOrFail();
        $this->assertSame(SubmissionStatus::Approved, $submission->status);

        app(ForceCompleteExam::class)->execute($exam);

        $this->assertSame(ExamStatus::Completed, $exam->fresh()->status);
        $this->assertSame(SubmissionStatus::Completed, $submission->fresh()->status);
    }

    #[Test]
    public function completing_an_expired_exam_marks_its_submission_completed(): void
    {
        [$schedule, $exam] = $this->makeActivatedSchedule();
        $submission = $schedule->submissions()->whereNotNull('exam_id')->firstOrFail();

        $exam->update(['window_end' => now()->subMinute()]);
        $this->assertSame(ExamStatus::Active, $exam->fresh()->status);

        $this->artisan('exams:complete-expired');

        $this->assertSame(ExamStatus::Completed, $exam->fresh()->status);
        $this->assertSame(SubmissionStatus::Completed, $submission->fresh()->status);
    }
}
