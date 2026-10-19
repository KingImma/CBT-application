<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Domains\Exams\Events\ExamCommentAdded;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamComment;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamReviewTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $teacher;

    protected Exam $exam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::firstOrCreate(['name' => 'school_admin', 'guard_name' => 'tenant']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'tenant']);

        $this->admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);
        $this->admin->assignRole('school_admin');

        $this->teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'User',
            'email' => 'teacher@example.com',
            'password' => bcrypt('password'),
            'role' => RoleType::Teacher->value,
            'is_active' => true,
        ]);
        $this->teacher->assignRole('teacher');

        // Exam defaults to Submitted — the only status in which review is permitted.
        $this->exam = $this->makeExam(ExamStatus::Submitted);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();

        parent::tearDown();
    }

    protected function makeExam(ExamStatus $status): Exam
    {
        $subject = Subject::firstOrCreate(['code' => 'MTH'], ['name' => 'Math']);
        $classLevel = ClassLevel::firstOrCreate(['slug' => 'grade-10'], ['name' => 'Grade 10']);
        $academicSession = AcademicSession::firstOrCreate(['name' => '2025/2026'], [
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'is_current' => true,
        ]);
        $term = Term::firstOrCreate(['name' => 'First Term', 'academic_session_id' => $academicSession->id], [
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-20',
            'is_current' => true,
        ]);

        return Exam::create([
            'title' => 'Review Test Exam',
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'term_id' => $term->id,
            'created_by' => $this->teacher->id,
            'type' => ExamType::Exam->value,
            'status' => $status->value,
            'duration_minutes' => 60,
            'total_marks' => 100,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'scheduled_start' => now()->subHour(),
            'settings' => ['require_attendance' => false],
        ]);
    }

    public function test_it_lets_an_admin_post_a_comment_on_a_submitted_exam_and_fires_the_notification_event(): void
    {
        Event::fake([ExamCommentAdded::class]);

        $response = $this->actingAs($this->admin, 'tenant')
            ->postJson("/api/exams/{$this->exam->id}/review/comments", [
                'comment' => 'Please double-check question 4, seems mis-marked.',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('exam_comments', [
            'exam_id' => $this->exam->id,
            'author_id' => $this->admin->id,
        ]);

        Event::assertDispatched(ExamCommentAdded::class, fn ($e) => $e->exam->id === $this->exam->id);
    }

    public function test_it_blocks_an_admin_from_commenting_on_an_exam_that_is_not_submitted(): void
    {
        $activeExam = $this->makeExam(ExamStatus::Active);

        $this->actingAs($this->admin, 'tenant')
            ->postJson("/api/exams/{$activeExam->id}/review/comments", [
                'comment' => 'Trying to review an active exam.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('exam_comments', [
            'exam_id' => $activeExam->id,
        ]);
    }

    public function test_it_lets_an_admin_view_the_review_payload_with_comments(): void
    {
        ExamComment::create([
            'exam_id' => $this->exam->id,
            'author_id' => $this->admin->id,
            'comment' => 'Existing review note.',
        ]);

        $this->actingAs($this->admin, 'tenant')
            ->getJson("/api/exams/{$this->exam->id}/review")
            ->assertOk()
            ->assertJsonPath('data.id', $this->exam->id)
            ->assertJsonPath('data.comments.0.comment', 'Existing review note.')
            ->assertJsonPath('data.comments.0.author.id', $this->admin->id);
    }

    public function test_it_blocks_a_teacher_from_viewing_the_admin_review_payload(): void
    {
        $this->actingAs($this->teacher, 'tenant')
            ->getJson("/api/exams/{$this->exam->id}/review")
            ->assertForbidden();
    }

    public function test_it_blocks_a_teacher_from_posting_a_review_comment(): void
    {
        $this->actingAs($this->teacher, 'tenant')
            ->postJson("/api/exams/{$this->exam->id}/review/comments", ['comment' => 'test'])
            ->assertForbidden();
    }

    public function test_it_requires_a_comment_body(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->postJson("/api/exams/{$this->exam->id}/review/comments", ['comment' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('comment');
    }

    public function test_it_requires_authentication_to_comment(): void
    {
        $this->postJson("/api/exams/{$this->exam->id}/review/comments", ['comment' => 'test'])
            ->assertUnauthorized();
    }
}
