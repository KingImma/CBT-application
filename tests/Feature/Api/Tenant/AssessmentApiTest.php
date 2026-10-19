<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Domains\Assessments\Actions\MaterializeAssessmentExams;
use App\Enums\AssessmentStatus;
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
 * The HTTP surface of the assessment flow: stable definitions (assessments),
 * per-term occurrences (schedules), the teacher authoring tier and the admin
 * review loop, plus the activation that materialises approved papers into
 * exams on the existing student exam path.
 */
class AssessmentApiTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $teacher;

    protected User $otherTeacher;

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
        $this->otherTeacher = $this->makeUser('other@test.com', RoleType::Teacher);

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

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    /** @return array<string,mixed> */
    protected function assessmentPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'End of Term Exam',
            'total_marks' => 100,
            'duration_minutes' => 90,
            'description' => 'Covers weeks one to six.',
        ], $overrides);
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

    /** @return array<string,mixed> */
    protected function schedulePayload(array $overrides = []): array
    {
        return array_merge([
            'class_level_id' => $this->classLevel->id,
            'question_submission_ends' => now()->addWeek()->toIso8601String(),
            'assessment_starts' => now()->addWeeks(2)->toIso8601String(),
            'assessment_ends' => now()->addWeeks(2)->addHours(2)->toIso8601String(),
        ], $overrides);
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

        $question->options()->createMany([
            ['label' => 'A', 'content' => '3', 'is_correct' => false, 'order' => 1],
            ['label' => 'B', 'content' => '4', 'is_correct' => true, 'order' => 2],
        ]);

        $submission->update(['total_marks' => (float) $submission->submissionQuestions()->sum('marks')]);

        return $question;
    }

    /** @return array<string,mixed> */
    protected function questionPayload(float $marks = 5): array
    {
        return [
            'type' => SubmissionQuestionType::Mcq->value,
            'content' => 'What is 2 + 2?',
            'marks' => $marks,
            'options' => [
                ['label' => 'A', 'content' => '3', 'is_correct' => false],
                ['label' => 'B', 'content' => '4', 'is_correct' => true],
            ],
        ];
    }

    // ---------------------------------------------------------------------
    // Definition tier (stable across terms)
    // ---------------------------------------------------------------------

    #[Test]
    public function admin_can_create_an_assessment_definition(): void
    {
        $this->actingAs($this->admin, 'tenant');

        $response = $this->postJson('/api/assessments', $this->assessmentPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'End of Term Exam');

        $this->assertSame(100.0, (float) $response->json('data.total_marks'));

        $this->assertDatabaseHas('assessments', [
            'title' => 'End of Term Exam',
            'created_by' => $this->admin->id,
            'total_marks' => 100,
        ]);
    }

    #[Test]
    public function a_teacher_cannot_create_an_assessment(): void
    {
        $this->actingAs($this->teacher, 'tenant');

        $this->postJson('/api/assessments', $this->assessmentPayload())->assertStatus(403);
    }

    #[Test]
    public function definitions_are_visible_to_every_staff_member(): void
    {
        // Global definitions: even a teacher with no assignments sees them.
        $this->makeAssessment();

        $this->actingAs($this->otherTeacher, 'tenant');

        $this->getJson('/api/assessments')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function an_admin_lists_every_assessment(): void
    {
        $this->makeAssessment();
        $this->makeAssessment(['title' => 'CA Test']);

        $this->actingAs($this->admin, 'tenant');

        $this->getJson('/api/assessments')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ---------------------------------------------------------------------
    // Scheduling tier (occurrences per session/term)
    // ---------------------------------------------------------------------

    #[Test]
    public function admin_can_schedule_an_assessment_into_the_current_term(): void
    {
        $assessment = $this->makeAssessment();
        $this->actingAs($this->admin, 'tenant');

        $response = $this->postJson("/api/assessments/{$assessment->id}/schedules", [
            'class_level_id' => $this->classLevel->id,
            'question_submission_ends' => now()->addWeek()->toIso8601String(),
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.question_submission_status', QuestionSubmissionStatus::Open->value)
            ->assertJsonPath('data.assessment_status', AssessmentStatus::Draft->value)
            ->assertJsonPath('data.classLevel.id', $this->classLevel->id);

        $this->assertSame((string) $this->term->id, (string) $response->json('data.term_id'));
        $this->assertSame((string) $this->academicSession->id, (string) $response->json('data.academic_session_id'));
    }

    #[Test]
    public function scheduling_requires_a_class_level(): void
    {
        $assessment = $this->makeAssessment();
        $this->actingAs($this->admin, 'tenant');

        $this->postJson("/api/assessments/{$assessment->id}/schedules", [
            'question_submission_ends' => now()->addWeek()->toIso8601String(),
        ])->assertStatus(422);
    }

    #[Test]
    public function scheduling_requires_a_future_question_deadline(): void
    {
        $assessment = $this->makeAssessment();
        $this->actingAs($this->admin, 'tenant');

        $this->postJson("/api/assessments/{$assessment->id}/schedules", [
            'class_level_id' => $this->classLevel->id,
            'question_submission_ends' => now()->subDay()->toIso8601String(),
        ])->assertStatus(422);
    }

    #[Test]
    public function an_assessment_cannot_be_scheduled_twice_for_the_same_class_and_term(): void
    {
        $assessment = $this->makeAssessment();
        $this->actingAs($this->admin, 'tenant');

        $payload = [
            'class_level_id' => $this->classLevel->id,
            'question_submission_ends' => now()->addWeek()->toIso8601String(),
        ];

        $this->postJson("/api/assessments/{$assessment->id}/schedules", $payload)->assertStatus(201);
        $this->postJson("/api/assessments/{$assessment->id}/schedules", $payload)->assertStatus(409);
    }

    #[Test]
    public function the_same_assessment_can_be_scheduled_for_two_classes_in_one_term(): void
    {
        // The reuse tree: End of Term Exam -> JSS1 + JSS2, same term.
        $assessment = $this->makeAssessment();
        $jss2 = ClassLevel::create(['name' => 'Grade 11', 'slug' => 'grade-11']);
        $this->actingAs($this->admin, 'tenant');

        $deadline = now()->addWeek()->toIso8601String();

        $this->postJson("/api/assessments/{$assessment->id}/schedules", [
            'class_level_id' => $this->classLevel->id,
            'question_submission_ends' => $deadline,
        ])->assertStatus(201);

        $this->postJson("/api/assessments/{$assessment->id}/schedules", [
            'class_level_id' => $jss2->id,
            'question_submission_ends' => $deadline,
        ])->assertStatus(201);
    }

    #[Test]
    public function a_teacher_cannot_schedule_an_assessment(): void
    {
        $assessment = $this->makeAssessment();
        $this->actingAs($this->teacher, 'tenant');

        $this->postJson("/api/assessments/{$assessment->id}/schedules", [
            'class_level_id' => $this->classLevel->id,
            'question_submission_ends' => now()->addWeek()->toIso8601String(),
        ])->assertStatus(403);
    }

    #[Test]
    public function an_admin_can_list_all_occurrences_of_an_assessment(): void
    {
        $assessment = $this->makeAssessment();
        $otherLevel = ClassLevel::create(['name' => 'Grade 11', 'slug' => 'grade-11']);

        $this->makeSchedule($assessment);
        $this->makeSchedule($assessment, ['class_level_id' => $otherLevel->id]);

        $this->actingAs($this->admin, 'tenant');

        $this->getJson("/api/assessments/{$assessment->id}/schedules")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function a_teacher_sees_only_schedules_for_their_assigned_class_levels(): void
    {
        $schedule = $this->makeSchedule();

        // otherTeacher has an assignment in setUp, but not for this subject's
        // class... they DO share the class level; strip that by pointing the
        // schedule at another level this teacher isn't assigned to.
        $unassigned = ClassLevel::create(['name' => 'Grade 12', 'slug' => 'grade-12']);
        $hidden = $this->makeSchedule(null, ['class_level_id' => $unassigned->id]);

        $this->actingAs($this->teacher, 'tenant');
        $this->getJson("/api/assessment-schedules/{$schedule->id}")->assertStatus(200);
        $this->getJson("/api/assessment-schedules/{$hidden->id}")->assertStatus(403);

        $visible = $this->getJson("/api/assessments/{$schedule->assessment_id}/schedules")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
        $this->assertSame($schedule->id, $visible->json('data.0.id'));
    }

    #[Test]
    public function admin_can_set_the_master_student_window_on_a_draft_schedule(): void
    {
        $schedule = $this->makeSchedule(null, ['assessment_starts' => null, 'assessment_ends' => null]);
        $this->actingAs($this->admin, 'tenant');

        $starts = now()->addDays(10);
        $ends = now()->addDays(10)->addHours(3);

        $this->patchJson("/api/assessment-schedules/{$schedule->id}", [
            'assessment_starts' => $starts->toIso8601String(),
            'assessment_ends' => $ends->toIso8601String(),
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.assessment_status', AssessmentStatus::Draft->value);

        $fresh = $schedule->fresh();
        $this->assertSame($starts->getTimestamp(), $fresh->assessment_starts->getTimestamp());
        $this->assertSame($ends->getTimestamp(), $fresh->assessment_ends->getTimestamp());
    }

    #[Test]
    public function a_master_window_cannot_end_before_it_starts(): void
    {
        $schedule = $this->makeSchedule();
        $this->actingAs($this->admin, 'tenant');

        $this->patchJson("/api/assessment-schedules/{$schedule->id}", [
            'assessment_starts' => now()->addDays(10)->toIso8601String(),
            'assessment_ends' => now()->addDays(9)->toIso8601String(),
        ])->assertStatus(422);
    }

    #[Test]
    public function deleting_a_draft_schedule_is_allowed_but_not_after_activation(): void
    {
        $draft = $this->makeSchedule();

        $active = $this->makeSchedule(null, [
            'question_submission_status' => QuestionSubmissionStatus::Closed->value,
            'assessment_status' => AssessmentStatus::Active->value,
        ]);

        $this->actingAs($this->admin, 'tenant');

        $this->deleteJson("/api/assessment-schedules/{$draft->id}")->assertStatus(200);
        $this->deleteJson("/api/assessment-schedules/{$active->id}")->assertStatus(409);
    }

    // ---------------------------------------------------------------------
    // Question-window lifecycle
    // ---------------------------------------------------------------------

    #[Test]
    public function admin_walks_the_question_window_through_close_and_reopen(): void
    {
        $schedule = $this->makeSchedule();
        $this->actingAs($this->admin, 'tenant');

        $this->postJson("/api/assessment-schedules/{$schedule->id}/close-submissions")
            ->assertStatus(200)
            ->assertJsonPath('data.question_submission_status', QuestionSubmissionStatus::Closed->value);

        $this->postJson("/api/assessment-schedules/{$schedule->id}/reopen", [
            'question_submission_ends' => now()->addDays(3)->toIso8601String(),
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.question_submission_status', QuestionSubmissionStatus::Open->value);
    }

    #[Test]
    public function reopening_with_a_past_deadline_is_a_conflict(): void
    {
        $schedule = $this->makeSchedule(null, ['question_submission_status' => QuestionSubmissionStatus::Closed->value]);
        $this->actingAs($this->admin, 'tenant');

        $this->postJson("/api/assessment-schedules/{$schedule->id}/reopen", [
            'question_submission_ends' => now()->subDay()->toIso8601String(),
        ])->assertStatus(409);
    }

    // ---------------------------------------------------------------------
    // Activation → materialised exams
    // ---------------------------------------------------------------------

    #[Test]
    public function activating_without_an_approved_submission_is_a_conflict(): void
    {
        $schedule = $this->makeSchedule();
        $schedule->closeSubmissions();

        $this->actingAs($this->admin, 'tenant');

        $this->postJson("/api/assessment-schedules/{$schedule->id}/activate")->assertStatus(409);
    }

    #[Test]
    public function activation_requires_a_slot_for_every_approved_subject(): void
    {
        $schedule = $this->makeSchedule();
        $submission = $this->makeSubmission($schedule);
        $this->addQuestion($submission, marks: 20);
        $submission->submitForReview();
        $submission->approve($this->admin->id);
        $schedule->closeSubmissions();
        // No ScheduleSubject slot placed for this approved subject.

        $this->actingAs($this->admin, 'tenant');

        $this->postJson("/api/assessment-schedules/{$schedule->id}/activate")->assertStatus(409);
        $this->assertNull($submission->fresh()->exam_id);
    }

    #[Test]
    public function activation_materialises_an_exam_from_each_approved_submission(): void
    {
        $schedule = $this->makeSchedule();
        $submission = $this->makeSubmission($schedule);
        $question = $this->addQuestion($submission, marks: 20);

        $submission->submitForReview();
        $submission->approve($this->admin->id);
        $schedule->closeSubmissions();

        $slot = $this->makeSlot($schedule);

        $this->actingAs($this->admin, 'tenant');

        $this->postJson("/api/assessment-schedules/{$schedule->id}/activate")
            ->assertStatus(200)
            ->assertJsonPath('data.assessment_status', AssessmentStatus::Active->value);

        $submission->refresh();
        $this->assertNotNull($submission->exam_id, 'the approved submission should be backed by an exam');

        $exam = Exam::findOrFail($submission->exam_id);
        $this->assertSame($submission->title, $exam->title);
        $this->assertSame($this->subject->id, $exam->subject_id);
        $this->assertSame(90, $exam->duration_minutes);
        $this->assertTrue($slot->starts_at->equalTo($exam->scheduled_start));
        $this->assertTrue($slot->ends_at->equalTo($exam->window_end));

        $examQuestion = $exam->examQuestions()->firstOrFail();
        $this->assertSame(20.0, (float) $examQuestion->marks);
        $this->assertSame($question->order, $examQuestion->order);
        $this->assertTrue($examQuestion->is_marks_locked);

        $materialised = $examQuestion->question;
        $this->assertSame('What is 2 + 2?', $materialised->content);
        $this->assertSame($question->type->toQuestionType()->value, $materialised->type);
        $this->assertCount(2, $materialised->options);
        $this->assertSame('4', $materialised->options->firstWhere('is_correct', true)->content);
    }

    #[Test]
    public function activation_is_idempotent_and_skips_unapproved_submissions(): void
    {
        $schedule = $this->makeSchedule();

        $approved = $this->makeSubmission($schedule);
        $this->addQuestion($approved, marks: 20);
        $approved->submitForReview();
        $approved->approve($this->admin->id);
        $this->makeSlot($schedule);

        $draftTeacher = $this->makeUser('draft-teacher@test.com', RoleType::Teacher);
        $physics = Subject::create(['name' => 'Physics', 'code' => 'PHY101']);
        $this->assignSubject($draftTeacher, $physics);
        $this->makeSubmission($schedule, ['teacher_id' => $draftTeacher->id, 'subject_id' => $physics->id]);

        $schedule->closeSubmissions();

        $this->actingAs($this->admin, 'tenant');
        $this->postJson("/api/assessment-schedules/{$schedule->id}/activate")->assertStatus(200);

        $this->assertDatabaseCount('exams', 1);

        // Re-running the materialiser must not duplicate the paper.
        app(MaterializeAssessmentExams::class)->execute($schedule->fresh());
        $this->assertDatabaseCount('exams', 1);
    }

    #[Test]
    public function completing_an_active_schedule_locks_its_lifecycle(): void
    {
        $schedule = $this->makeSchedule(null, ['assessment_status' => AssessmentStatus::Active->value]);
        $this->actingAs($this->admin, 'tenant');

        $this->postJson("/api/assessment-schedules/{$schedule->id}/complete")
            ->assertStatus(200)
            ->assertJsonPath('data.assessment_status', AssessmentStatus::Completed->value);

        // A completed schedule cannot be reactivated.
        $this->postJson("/api/assessment-schedules/{$schedule->id}/activate")->assertStatus(409);
    }

    // ---------------------------------------------------------------------
    // Teacher authoring tier
    // ---------------------------------------------------------------------

    #[Test]
    public function an_assigned_teacher_can_author_a_submission_with_questions(): void
    {
        $schedule = $this->makeSchedule();
        $this->actingAs($this->teacher, 'tenant');

        $response = $this->postJson("/api/assessment-schedules/{$schedule->id}/submissions", [
            'subject_id' => $this->subject->id,
            'title' => 'Maths Paper 1',
            'description' => 'Algebra and geometry.',
        ])->assertStatus(201)
            ->assertJsonPath('data.status', SubmissionStatus::Draft->value);

        $submissionId = $response->json('data.id');

        $this->postJson("/api/submissions/{$submissionId}/questions", $this->questionPayload(marks: 7))
            ->assertStatus(201)
            ->assertJsonPath('data.question.marks', 7);

        $this->assertSame(7.0, (float) Submission::find($submissionId)->total_marks);
        $this->assertDatabaseCount('submission_question_options', 2);
    }

    #[Test]
    public function a_teacher_can_list_submissions_of_a_schedule_they_can_see(): void
    {
        $schedule = $this->makeSchedule();
        $this->makeSubmission($schedule);
        $this->makeSubmission($schedule, ['teacher_id' => $this->otherTeacher->id]);

        $this->actingAs($this->teacher, 'tenant');

        $this->getJson("/api/assessment-schedules/{$schedule->id}/submissions")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    #[Test]
    public function a_teacher_not_assigned_to_the_subject_cannot_author_a_submission(): void
    {
        $schedule = $this->makeSchedule();
        $this->actingAs($this->otherTeacher, 'tenant');

        $this->postJson("/api/assessment-schedules/{$schedule->id}/submissions", [
            'subject_id' => $this->subject->id,
            'title' => 'Maths Paper 1',
        ])->assertStatus(409);
    }

    #[Test]
    public function a_teacher_cannot_author_a_second_submission_for_the_same_subject(): void
    {
        $schedule = $this->makeSchedule();
        $this->actingAs($this->teacher, 'tenant');

        $payload = ['subject_id' => $this->subject->id, 'title' => 'Maths Paper 1'];

        $this->postJson("/api/assessment-schedules/{$schedule->id}/submissions", $payload)->assertStatus(201);
        $this->postJson("/api/assessment-schedules/{$schedule->id}/submissions", $payload)->assertStatus(409);
    }

    #[Test]
    public function a_teacher_cannot_author_a_submission_once_the_window_is_closed(): void
    {
        $schedule = $this->makeSchedule();
        $schedule->closeSubmissions();

        $this->actingAs($this->teacher, 'tenant');

        $this->postJson("/api/assessment-schedules/{$schedule->id}/submissions", [
            'subject_id' => $this->subject->id,
            'title' => 'Maths Paper 1',
        ])->assertStatus(409);
    }

    #[Test]
    public function a_teacher_cannot_touch_another_teachers_submission(): void
    {
        $submission = $this->makeSubmission();
        $this->assignSubject($this->otherTeacher);

        $this->actingAs($this->otherTeacher, 'tenant');

        $this->getJson("/api/submissions/{$submission->id}")->assertStatus(403);
        $this->putJson("/api/submissions/{$submission->id}", ['title' => 'Hijacked'])->assertStatus(403);
        $this->postJson("/api/submissions/{$submission->id}/questions", $this->questionPayload())
            ->assertStatus(403);
    }

    #[Test]
    public function submitting_an_empty_submission_is_a_conflict(): void
    {
        $submission = $this->makeSubmission();
        $this->actingAs($this->teacher, 'tenant');

        $this->postJson("/api/submissions/{$submission->id}/submit-for-review")->assertStatus(409);
    }

    #[Test]
    public function submitting_over_the_assessments_marks_cap_is_a_conflict(): void
    {
        $submission = $this->makeSubmission();
        $this->addQuestion($submission, marks: 150);

        $this->actingAs($this->teacher, 'tenant');

        $this->postJson("/api/submissions/{$submission->id}/submit-for-review")->assertStatus(409);
    }

    #[Test]
    public function submitting_after_the_deadline_is_a_conflict(): void
    {
        $submission = $this->makeSubmission();
        $this->addQuestion($submission);

        $this->travelTo(now()->addWeeks(2));
        $this->actingAs($this->teacher, 'tenant');

        $this->postJson("/api/submissions/{$submission->id}/submit-for-review")->assertStatus(409);
    }

    // ---------------------------------------------------------------------
    // Admin review loop
    // ---------------------------------------------------------------------

    #[Test]
    public function the_review_loop_runs_from_submit_through_changes_to_approval(): void
    {
        $submission = $this->makeSubmission();
        $this->addQuestion($submission, marks: 20);

        $this->actingAs($this->teacher, 'tenant');
        $this->postJson("/api/submissions/{$submission->id}/submit-for-review")
            ->assertStatus(200)
            ->assertJsonPath('data.status', SubmissionStatus::Submitted->value);

        $this->actingAs($this->admin, 'tenant');
        $this->postJson("/api/submissions/{$submission->id}/request-changes", [
            'comment' => 'Question 1 needs a clearer stem.',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', SubmissionStatus::ChangesRequested->value);

        $this->assertDatabaseHas('submission_comments', [
            'submission_id' => $submission->id,
            'author_id' => $this->admin->id,
            'body' => 'Question 1 needs a clearer stem.',
        ]);

        $this->actingAs($this->teacher, 'tenant');
        $this->postJson("/api/submissions/{$submission->id}/submit-for-review")
            ->assertStatus(200)
            ->assertJsonPath('data.status', SubmissionStatus::Submitted->value);

        $this->actingAs($this->admin, 'tenant');
        $this->postJson("/api/submissions/{$submission->id}/approve")
            ->assertStatus(200)
            ->assertJsonPath('data.status', SubmissionStatus::Approved->value);

        $this->assertNotNull($submission->fresh()->approved_at);
    }

    #[Test]
    public function requesting_changes_on_a_draft_is_forbidden(): void
    {
        $submission = $this->makeSubmission();
        $this->actingAs($this->admin, 'tenant');

        $this->postJson("/api/submissions/{$submission->id}/request-changes", ['comment' => 'No.'])
            ->assertStatus(403);
    }

    #[Test]
    public function a_teacher_cannot_approve_their_own_submission(): void
    {
        $submission = $this->makeSubmission(null, ['status' => SubmissionStatus::Submitted->value]);
        $this->actingAs($this->teacher, 'tenant');

        $this->postJson("/api/submissions/{$submission->id}/approve")->assertStatus(403);
    }

    #[Test]
    public function an_approved_submission_is_locked_to_its_author(): void
    {
        $submission = $this->makeSubmission(null, ['status' => SubmissionStatus::Approved->value]);
        $this->actingAs($this->teacher, 'tenant');

        $this->putJson("/api/submissions/{$submission->id}", ['title' => 'Late edit'])->assertStatus(403);
        $this->postJson("/api/submissions/{$submission->id}/questions", $this->questionPayload())
            ->assertStatus(403);
    }
}
