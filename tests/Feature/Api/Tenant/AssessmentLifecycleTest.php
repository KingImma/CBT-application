<?php

namespace Tests\Feature\Api\Tenant;

use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Question;
use App\Models\Tenant\SchoolSetting;
use App\Models\Tenant\Subject;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssessmentLifecycleTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $teacher;

    protected User $otherTeacher;

    protected User $student;

    protected Subject $subject;

    protected Subject $otherSubject;

    protected ClassLevel $classLevel;

    protected ClassArm $classArm;

    protected AcademicSession $academicSession;

    protected Term $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->subject = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
        ]);

        $this->otherSubject = Subject::create([
            'name' => 'English',
            'code' => 'ENG101',
        ]);

        $this->classLevel = ClassLevel::create([
            'name' => 'Grade 10',
            'slug' => 'grade-10',
        ]);

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

        $this->otherTeacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'Two',
            'email' => 'teacher2@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $this->otherTeacher->assignRole('teacher');

        $this->student = User::create([
            'first_name' => 'Student',
            'last_name' => 'One',
            'email' => 'student@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Student->value,
            'is_active' => true,
        ]);
        $this->student->assignRole('student');

        // Create a student profile with required fields
        $this->student->studentProfile()->create([
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'guardian_email' => 'guardian@test.com',
            'admission_number' => 'STU001',
        ]);

        // Assign teacher to subject and class via TeacherSubjectAssignment
        TeacherSubjectAssignment::create([
            'user_id' => $this->teacher->id,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'academic_session_id' => $this->academicSession->id,
        ]);

        // Assign other teacher to a different subject
        TeacherSubjectAssignment::create([
            'user_id' => $this->otherTeacher->id,
            'subject_id' => $this->otherSubject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'academic_session_id' => $this->academicSession->id,
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    protected function actingAsTenant(User $user): static
    {
        Sanctum::actingAs($user, ['*'], 'tenant');

        return $this;
    }

    #[Test]
    public function admin_can_create_assessment_with_valid_metadata(): void
    {

        $this->actingAsTenant($this->admin);

        $response = $this->postJson('/api/exams', [
            'title' => 'Mid-Term Mathematics Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'duration_minutes' => 120,
            'pass_mark' => 50.00,
        ]);

        $response->assertStatus(201);
        $this->assertEquals('draft', $response->json('data.status'));
    }

    #[Test]
    public function creation_fails_when_required_fields_are_missing(): void
    {
        $this->actingAsTenant($this->admin);

        $response = $this->postJson('/api/exams', [
            'title' => 'Incomplete Exam',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject_id', 'class_level_id', 'term_id', 'type', 'duration_minutes']);
    }

    #[Test]
    public function newly_created_assessment_status_is_draft(): void
    {
        $this->actingAsTenant($this->admin);

        $response = $this->postJson('/api/exams', [
            'title' => 'Draft Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'duration_minutes' => 120,
            'pass_mark' => 50.00,
        ]);

        $examId = $response->json('data.id');
        $exam = Exam::find($examId);

        $this->assertEquals('draft', $exam->status);
    }

    #[Test]
    public function admin_can_edit_metadata_while_status_is_draft(): void
    {
        $this->actingAsTenant($this->admin);

        $response = $this->postJson('/api/exams', [
            'title' => 'Original Title',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'duration_minutes' => 120,
            'pass_mark' => 50.00,
        ]);

        $examId = $response->json('data.id');

        $response = $this->patchJson("/api/exams/{$examId}", [
            'title' => 'Updated Title',
            'duration_minutes' => 90,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('Updated Title', $response->json('data.title'));
        $this->assertEquals(90, $response->json('data.duration_minutes'));
    }

    #[Test]
    public function admin_can_delete_draft_assessment_only_if_no_student_attempt_exists(): void
    {
        $this->actingAsTenant($this->admin);

        // Create exam
        $response = $this->postJson('/api/exams', [
            'title' => 'Deletable Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'duration_minutes' => 60,
            'pass_mark' => 50.00,
        ]);

        $examId = $response->json('data.id');
        $exam = Exam::find($examId);

        // Add a question to the exam first
        $question = Question::create([
            'content' => 'Sample question',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);

        $exam->examQuestions()->create([
            'question_id' => $question->id,
            'order' => 1,
            'marks' => 5.00,
        ]);

        // Delete should succeed (no attempts)
        $response = $this->deleteJson("/api/exams/{$examId}");
        $response->assertStatus(200);
        $this->assertNull(Exam::find($examId));
    }

    #[Test]
    public function teacher_can_add_one_question_from_their_bank_to_a_draft_assessment(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        $question = Question::create([
            'content' => 'What is 2+2?',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);

        $response = $this->postJson("/api/exams/{$exam->id}/questions", [
            'question_id' => $question->id,
        ]);

        $response->assertStatus(201);
        $this->assertEquals('Question added to exam.', $response->json('message'));
    }

    #[Test]
    public function teacher_cannot_add_duplicate_question_to_the_same_assessment(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        $question = Question::create([
            'content' => 'What is the capital of France?',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);

        // Add the question first time
        $this->postJson("/api/exams/{$exam->id}/questions", [
            'question_id' => $question->id,
        ]);

        // Try to add the same question again
        $response = $this->postJson("/api/exams/{$exam->id}/questions", [
            'question_id' => $question->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('already been added', $response->json('message'));
    }

    #[Test]
    public function teacher_cannot_add_a_question_from_another_teacher_bank(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        // Create a question owned by otherTeacher (different subject/class assignment)
        $question = Question::create([
            'content' => 'Other teacher question',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->otherSubject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->otherTeacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);

        $response = $this->postJson("/api/exams/{$exam->id}/questions", [
            'question_id' => $question->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('question bank', strtolower($response->json('message')));
    }

    #[Test]
    public function teacher_cannot_add_questions_to_an_active_assessment(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        $question = Question::create([
            'content' => 'Question for active exam',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);

        // Manually set exam status to active (simulating activation)
        $exam->status = 'active';
        $exam->save();

        $response = $this->postJson("/api/exams/{$exam->id}/questions", [
            'question_id' => $question->id,
        ]);

        $this->assertContains($response->status(), [403, 422]);
    }

    #[Test]
    public function teacher_can_remove_a_question_they_added_before_activation(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        $question = Question::create([
            'content' => 'Removable question',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);

        // Add the question
        $addResponse = $this->postJson("/api/exams/{$exam->id}/questions", [
            'question_id' => $question->id,
        ]);
        $addResponse->assertStatus(201);

        // Remove the question
        $response = $this->deleteJson("/api/exams/{$exam->id}/questions/{$question->id}");
        $response->assertStatus(200);
        $this->assertEquals(0, $exam->fresh()->examQuestions()->count());
    }

    #[Test]
    public function teacher_cannot_exceed_assessment_max_score(): void
    {
        $this->actingAsTenant($this->teacher);

        // Lower max score for testing
        SchoolSetting::where('key', 'exam_max_score')
            ->update(['value' => '10']);

        $exam = $this->createDraftExam($this->teacher);

        $q1 = Question::create([
            'content' => 'Question 1',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);
        $this->postJson("/api/exams/{$exam->id}/questions", [
            'question_id' => $q1->id,
        ])->assertStatus(201);

        $q2 = Question::create([
            'content' => 'Question 2',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);
        $this->postJson("/api/exams/{$exam->id}/questions", [
            'question_id' => $q2->id,
        ])->assertStatus(201);

        $q3 = Question::create([
            'content' => 'Question 3',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);
        $response = $this->postJson("/api/exams/{$exam->id}/questions", [
            'question_id' => $q3->id,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('exceed', strtolower($response->json('message')));
    }

    #[Test]
    public function teacher_views_only_own_question_bank(): void
    {
        $this->actingAsTenant($this->teacher);

        Question::create([
            'content' => 'Teacher One question',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);

        Question::create([
            'content' => 'Other Teacher question',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->otherSubject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->otherTeacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);

        $response = $this->getJson('/api/questions');

        $response->assertStatus(200);
        $questions = $response->json('data');
        $this->assertCount(1, $questions);
        $this->assertEquals('Teacher One question', $questions[0]['content']);
    }

    #[Test]
    public function teacher_can_submit_draft_assessment_for_review(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        // Add a question
        $this->addQuestionToExam($exam, $this->teacher);

        $response = $this->postJson("/api/exams/{$exam->id}/submit-for-review");

        $response->assertStatus(200);
        $this->assertEquals('submitted', $response->json('data.status'));
    }

    #[Test]
    public function submission_fails_when_no_questions_added(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        // No question added
        $response = $this->postJson("/api/exams/{$exam->id}/submit-for-review");

        $response->assertStatus(422);
        $this->assertStringContainsString('question', strtolower($response->json('message')));
    }

    #[Test]
    public function admin_can_activate_submitted_assessment(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        // Teacher adds enough questions to meet pass_mark requirement
        $this->addQuestionToExam($exam, $this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        // Admin activates
        $this->actingAsTenant($this->admin);
        $response = $this->postJson("/api/exams/{$exam->id}/activate");

        $response->assertStatus(200);
        $this->assertEquals('active', $response->json('data.status'));
    }

    #[Test]
    public function teacher_cannot_submit_already_submitted_assessment(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        // Submit again - policy blocks with 403 before action logic
        $response = $this->postJson("/api/exams/{$exam->id}/submit-for-review");

        $this->assertContains($response->status(), [403, 422]);
    }

    #[Test]
    public function teacher_cannot_modify_assessment_after_submission(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        // Try to edit metadata
        $response = $this->patchJson("/api/exams/{$exam->id}", [
            'title' => 'Should not update',
        ]);
        $this->assertContains($response->status(), [403, 422]);

        // Try to add another question
        $extraQuestion = Question::create([
            'content' => 'Extra question',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);
        $response = $this->postJson("/api/exams/{$exam->id}/questions", [
            'question_id' => $extraQuestion->id,
        ]);
        $this->assertContains($response->status(), [403, 422]);
    }

    #[Test]
    public function admin_can_lock_active_assessment(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        for ($i = 0; $i < 10; $i++) {
            $this->addQuestionToExam($exam, $this->teacher);
        }
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        // Admin activates then locks
        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/activate")->assertStatus(200);

        $response = $this->postJson("/api/exams/{$exam->id}/lock");
        $response->assertStatus(200);
        $this->assertEquals('locked', $response->json('data.status'));
    }

    #[Test]
    public function admin_can_lock_submitted_assessment(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        // Admin locks without activating first
        $this->actingAsTenant($this->admin);
        $response = $this->postJson("/api/exams/{$exam->id}/lock");
        $response->assertStatus(200);
        $this->assertEquals('locked', $response->json('data.status'));
    }

    #[Test]
    public function teacher_cannot_lock_assessment(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        // Teacher tries to lock
        $this->actingAsTenant($this->teacher);
        $response = $this->postJson("/api/exams/{$exam->id}/lock");
        $this->assertContains($response->status(), [403, 404]);
    }

    #[Test]
    public function activation_fails_when_pass_mark_exceeds_total_marks(): void
    {
        $exam = $this->createDraftExam($this->teacher);
        $this->addQuestionToExam($exam, $this->teacher); // 5 marks total
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        $this->actingAsTenant($this->admin);
        $response = $this->postJson("/api/exams/{$exam->id}/activate");
        $response->assertStatus(422);
        $this->assertStringContainsString('pass mark', strtolower($response->json('message')));
    }

    #[Test]
    public function student_can_view_available_active_assessments(): void
    {
        $exam = $this->createDraftExam($this->teacher, ['pass_mark' => 5.00]);

        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/activate")->assertStatus(200);
        $exam->fresh()->update([
            'session_started_at' => now(),
            'session_duration_minutes' => 120,
        ]);

        $this->actingAsTenant($this->student);
        $response = $this->getJson('/student/exams/available');
        $response->assertStatus(200);
        $examIds = collect($response->json('data'))->pluck('id');
        $this->assertTrue($examIds->contains($exam->id));
    }

    #[Test]
    public function student_cannot_view_non_active_assessments(): void
    {
        $draftExam = $this->createDraftExam($this->teacher);
        $this->actingAsTenant($this->student);

        $response = $this->getJson('/student/exams/available');
        $response->assertStatus(200);
        $examIds = collect($response->json('data'))->pluck('id');
        $this->assertFalse($examIds->contains($draftExam->id));
    }

    #[Test]
    public function student_can_start_attempt_on_active_assessment(): void
    {
        $exam = $this->createDraftExam($this->teacher, ['pass_mark' => 5.00]);

        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/activate")->assertStatus(200);
        $exam->fresh()->update([
            'session_started_at' => now()->subMinutes(5),
            'session_duration_minutes' => 120,
            'settings' => array_merge($exam->settings->toArray(), ['require_attendance' => false]),
        ]);

        $this->actingAsTenant($this->student);
        $response = $this->postJson("/student/exams/{$exam->id}/start");
        $response->assertStatus(201);
        $this->assertArrayHasKey('attempt', $response->json('data'));
    }

    #[Test]
    public function student_cannot_start_attempt_on_draft_assessment(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        $this->actingAsTenant($this->student);
        $response = $this->postJson("/student/exams/{$exam->id}/start");
        $response->assertStatus(422);
        $this->assertStringContainsString('not active', strtolower($response->json('message')));
    }

    #[Test]
    public function student_max_attempts_are_enforced(): void
    {
        $exam = $this->createDraftExam($this->teacher, ['pass_mark' => 5.00]);

        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/activate")->assertStatus(200);
        $exam->fresh()->update([
            'session_started_at' => now()->subMinutes(5),
            'session_duration_minutes' => 120,
            'max_attempts' => 1,
            'settings' => array_merge($exam->fresh()->settings->toArray(), ['require_attendance' => false]),
        ]);

        $this->actingAsTenant($this->student);
        // First attempt should succeed
        $this->postJson("/student/exams/{$exam->id}/start")->assertStatus(201);

        // Second attempt should fail
        $response = $this->postJson("/student/exams/{$exam->id}/start");
        $response->assertStatus(422);
        $this->assertStringContainsString('attempt', strtolower($response->json('message')));
    }

    #[Test]
    public function invalid_status_transitions_are_rejected(): void
    {
        $exam = $this->createDraftExam($this->teacher);

        // Cannot activate a draft directly (must go through submit)
        $this->actingAsTenant($this->admin);
        $response = $this->postJson("/api/exams/{$exam->id}/activate");
        $response->assertStatus(403);

        // Cannot submit an already submitted exam
        $this->actingAsTenant($this->teacher);
        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);
        $response = $this->postJson("/api/exams/{$exam->id}/submit-for-review");
        $this->assertContains($response->status(), [403, 422]);

        // Once locked, cannot activate
        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/lock")->assertStatus(200);
        $response = $this->postJson("/api/exams/{$exam->id}/activate");
        $this->assertContains($response->status(), [403, 422]);
    }

    #[Test]
    public function active_assessment_cannot_be_deleted(): void
    {
        $exam = $this->createDraftExam($this->teacher, ['pass_mark' => 5.00]);

        $this->addQuestionToExam($exam, $this->teacher);
        $this->postJson("/api/exams/{$exam->id}/submit-for-review")->assertStatus(200);

        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/activate")->assertStatus(200);

        // Try to delete
        $response = $this->deleteJson("/api/exams/{$exam->id}");
        $this->assertContains($response->status(), [403, 422]);
    }

    #[Test]
    public function full_end_to_end_exam_flow_with_timing_and_grading(): void
    {
        // 1. Teacher creates exam with MVP settings (teacher=creator for policy)
        $exam = $this->createDraftExam($this->teacher, [
            'pass_mark' => 5.00,
            'max_attempts' => 1,
            'settings' => [
                'require_attendance' => false,
                'show_result_immediately' => true,
            ],
        ]);
        $examId = $exam->id;

        // 2. Teacher creates 3 mcq_single questions with options
        $q1 = $this->createMcqQuestion('What is 2+2?', ['1', '2', '3', '4'], 1);
        $q2 = $this->createMcqQuestion('What is the capital of France?', ['London', 'Paris', 'Berlin', 'Madrid'], 1);
        $q3 = $this->createMcqQuestion('Which planet is closest to the sun?', ['Venus', 'Mars', 'Mercury', 'Earth'], 2);

        // 3. Add questions to exam
        $exam->examQuestions()->createMany([
            ['question_id' => $q1->id, 'order' => 1, 'marks' => 5.00],
            ['question_id' => $q2->id, 'order' => 2, 'marks' => 5.00],
            ['question_id' => $q3->id, 'order' => 3, 'marks' => 5.00],
        ]);
        $exam->update(['total_marks' => $exam->examQuestions()->sum('marks')]);
        $this->assertEquals(15.00, $exam->fresh()->total_marks);

        // 4. Teacher submits for review
        $response = $this->postJson("/api/exams/{$examId}/submit-for-review");
        $response->assertStatus(200);
        $this->assertEquals('submitted', $response->json('data.status'));

        // 5. Admin activates with session timing
        $this->actingAsTenant($this->admin);
        $response = $this->postJson("/api/exams/{$examId}/activate");
        $response->assertStatus(200);
        $this->assertEquals('active', $response->json('data.status'));

        $exam->fresh()->update([
            'session_started_at' => now()->subMinutes(5),
            'session_duration_minutes' => 30,
        ]);

        // 6. Student starts attempt
        $this->actingAsTenant($this->student);
        $response = $this->postJson("/student/exams/{$examId}/start");
        $response->assertStatus(201);
        $attemptId = $response->json('data.attempt.id');
        $this->assertNotNull($attemptId);
        $this->assertEquals('in_progress', $response->json('data.attempt.status'));

        $questions = $response->json('data.questions');
        $this->assertCount(3, $questions);

        // 7. Verify timer is running
        $response = $this->getJson("/student/exams/attempts/{$attemptId}/time-remaining");
        $response->assertStatus(200);
        $remaining = $response->json('data.remaining_seconds');
        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(30 * 60, $remaining);

        // 8. Save answers: Q1 correct, Q2 correct, Q3 wrong
        $q1Correct = $q1->options()->where('is_correct', true)->first();
        $q2Correct = $q2->options()->where('is_correct', true)->first();
        $q3Wrong = $q3->options()->where('is_correct', false)->first();

        $this->putJson("/student/exams/attempts/{$attemptId}/answers/{$q1->id}", [
            'selected_option_ids' => [$q1Correct->id],
            'time_spent_seconds' => 30,
        ])->assertStatus(200);

        $this->putJson("/student/exams/attempts/{$attemptId}/answers/{$q2->id}", [
            'selected_option_ids' => [$q2Correct->id],
            'time_spent_seconds' => 45,
        ])->assertStatus(200);

        $this->putJson("/student/exams/attempts/{$attemptId}/answers/{$q3->id}", [
            'selected_option_ids' => [$q3Wrong->id],
            'time_spent_seconds' => 20,
        ])->assertStatus(200);

        // 9. Submit
        $response = $this->postJson("/student/exams/attempts/{$attemptId}/submit");
        $response->assertStatus(200);
        $attemptData = $response->json('data.attempt');

        // 10. Verify auto-grading
        $this->assertEquals('graded', $attemptData['status']);
        $this->assertEquals(10.00, (float) $attemptData['total_score']);
        $this->assertEquals(66.67, round((float) $attemptData['percentage_score'], 2));
        $this->assertNotNull($attemptData['time_spent_seconds']);
        $this->assertGreaterThan(0, (int) $attemptData['time_spent_seconds']);

        // 11. Result accessible (show_result_immediately)
        $response = $this->getJson("/student/exams/attempts/{$attemptId}/result");
        $response->assertStatus(200);
        $this->assertArrayHasKey('total_score', $response->json('data'));

        // 12. Max attempts enforced
        $response = $this->postJson("/student/exams/{$examId}/start");
        $response->assertStatus(422);
        $this->assertStringContainsString('attempt', strtolower($response->json('message')));
    }

    protected function createDraftExam(?User $asUser = null, array $overrides = []): Exam
    {
        $user = $asUser ?? $this->admin;
        $this->actingAsTenant($user);

        $response = $this->postJson('/api/exams', array_merge([
            'title' => 'Test Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'duration_minutes' => 60,
            'pass_mark' => 50.00,
        ], $overrides));

        return Exam::find($response->json('data.id'));
    }

    protected function addQuestionToExam(Exam $exam, ?User $owner = null): Question
    {
        $user = $owner ?? $this->teacher;
        $question = Question::create([
            'content' => 'What is 2+2?',
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $user->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);

        $exam->examQuestions()->create([
            'question_id' => $question->id,
            'order' => $exam->examQuestions()->max('order') + 1,
            'marks' => 5.00,
        ]);

        $exam->update(['total_marks' => $exam->examQuestions()->sum('marks')]);

        return $question;
    }

    protected function createMcqQuestion(string $content, array $optionContents, int $correctIndex): Question
    {
        $question = Question::create([
            'content' => $content,
            'type' => 'mcq_single',
            'default_marks' => 5,
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'created_by' => $this->teacher->id,
            'is_active' => true,
            'academic_session_id' => $this->academicSession->id,
            'term_id' => $this->term->id,
        ]);

        foreach ($optionContents as $index => $optionContent) {
            $question->options()->create([
                'content' => $optionContent,
                'is_correct' => $index === $correctIndex,
                'order' => $index + 1,
                'label' => chr(65 + $index),
            ]);
        }

        return $question;
    }
}
