<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Domains\Exams\Events\ExamCommentReplied;
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
use App\Notifications\InAppNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherExamReviewTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $teacher;

    protected User $otherTeacher;

    protected Exam $exam;

    protected ExamComment $adminComment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        Role::firstOrCreate(['name' => 'school_admin', 'guard_name' => 'tenant']);
        Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'tenant']);

        $this->admin = $this->makeUser('admin@example.com', RoleType::SchoolAdmin, 'school_admin');
        $this->teacher = $this->makeUser('teacher@example.com', RoleType::Teacher, 'teacher');
        $this->otherTeacher = $this->makeUser('other@example.com', RoleType::Teacher, 'teacher');

        $subject = Subject::create(['name' => 'Math', 'code' => 'MTH']);
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

        // Owned by $this->teacher.
        $this->exam = Exam::create([
            'title' => 'Review Test Exam',
            'subject_id' => $subject->id,
            'class_level_id' => $classLevel->id,
            'term_id' => $term->id,
            'created_by' => $this->teacher->id,
            'type' => ExamType::Exam->value,
            'status' => ExamStatus::Draft->value,
            'duration_minutes' => 60,
            'total_marks' => 100,
            'pass_mark' => 50,
            'max_attempts' => 1,
            'scheduled_start' => now()->subHour(),
            'settings' => ['require_attendance' => false],
        ]);

        // A top-level admin comment the teacher can reply to.
        $this->adminComment = ExamComment::create([
            'exam_id' => $this->exam->id,
            'author_id' => $this->admin->id,
            'comment' => 'Please fix question 4.',
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();

        parent::tearDown();
    }

    protected function makeUser(string $email, RoleType $role, string $roleName): User
    {
        $user = User::create([
            'first_name' => ucfirst(explode('@', $email)[0]),
            'last_name' => 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'role' => $role->value,
            'is_active' => true,
        ]);
        $user->assignRole($roleName);

        return $user;
    }

    public function test_it_lets_the_owning_teacher_view_the_review_thread_with_nested_replies(): void
    {
        ExamComment::create([
            'exam_id' => $this->exam->id,
            'author_id' => $this->teacher->id,
            'parent_id' => $this->adminComment->id,
            'comment' => 'Done, fixed it.',
        ]);

        $this->actingAs($this->teacher, 'tenant')
            ->getJson("/api/exams/{$this->exam->id}/review/thread")
            ->assertOk()
            ->assertJsonPath('data.comments.0.id', $this->adminComment->id)
            ->assertJsonPath('data.comments.0.replies.0.comment', 'Done, fixed it.');
    }

    public function test_it_lets_the_owning_teacher_reply_to_an_admin_comment_and_fires_the_event(): void
    {
        Event::fake([ExamCommentReplied::class]);

        $this->actingAs($this->teacher, 'tenant')
            ->postJson("/api/exams/{$this->exam->id}/review/comments/{$this->adminComment->id}/replies", [
                'reply' => 'Addressed the feedback on question 4.',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('exam_comments', [
            'exam_id' => $this->exam->id,
            'parent_id' => $this->adminComment->id,
            'author_id' => $this->teacher->id,
            'comment' => 'Addressed the feedback on question 4.',
        ]);

        Event::assertDispatched(ExamCommentReplied::class, fn ($e) => $e->reply->parent_id === $this->adminComment->id);
    }

    public function test_replying_marks_the_parent_comment_resolved(): void
    {
        $this->actingAs($this->teacher, 'tenant')
            ->postJson("/api/exams/{$this->exam->id}/review/comments/{$this->adminComment->id}/replies", [
                'reply' => 'Handled.',
            ])
            ->assertCreated();

        $this->assertNotNull($this->adminComment->fresh()->resolved_at);
    }

    public function test_replying_notifies_the_comment_author(): void
    {
        Notification::fake();

        $this->actingAs($this->teacher, 'tenant')
            ->postJson("/api/exams/{$this->exam->id}/review/comments/{$this->adminComment->id}/replies", [
                'reply' => 'Handled.',
            ])
            ->assertCreated();

        Notification::assertSentTo($this->admin, InAppNotification::class);
    }

    public function test_it_forbids_a_teacher_who_does_not_own_the_exam_from_replying(): void
    {
        $this->actingAs($this->otherTeacher, 'tenant')
            ->postJson("/api/exams/{$this->exam->id}/review/comments/{$this->adminComment->id}/replies", [
                'reply' => 'Not my exam.',
            ])
            ->assertForbidden();
    }

    public function test_it_forbids_a_teacher_who_does_not_own_the_exam_from_viewing_the_thread(): void
    {
        $this->actingAs($this->otherTeacher, 'tenant')
            ->getJson("/api/exams/{$this->exam->id}/review/thread")
            ->assertForbidden();
    }

    public function test_it_blocks_an_admin_from_the_teacher_reply_endpoint(): void
    {
        $this->actingAs($this->admin, 'tenant')
            ->postJson("/api/exams/{$this->exam->id}/review/comments/{$this->adminComment->id}/replies", [
                'reply' => 'Admins use the comment endpoint.',
            ])
            ->assertForbidden();
    }

    public function test_it_cannot_reply_to_a_reply(): void
    {
        $reply = ExamComment::create([
            'exam_id' => $this->exam->id,
            'author_id' => $this->teacher->id,
            'parent_id' => $this->adminComment->id,
            'comment' => 'First reply.',
        ]);

        $this->actingAs($this->teacher, 'tenant')
            ->postJson("/api/exams/{$this->exam->id}/review/comments/{$reply->id}/replies", [
                'reply' => 'Reply to a reply.',
            ])
            ->assertNotFound();
    }

    public function test_it_requires_a_reply_body(): void
    {
        $this->actingAs($this->teacher, 'tenant')
            ->postJson("/api/exams/{$this->exam->id}/review/comments/{$this->adminComment->id}/replies", [
                'reply' => '',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('reply');
    }

    public function test_it_requires_authentication_to_reply(): void
    {
        $this->postJson("/api/exams/{$this->exam->id}/review/comments/{$this->adminComment->id}/replies", [
            'reply' => 'test',
        ])->assertUnauthorized();
    }
}
