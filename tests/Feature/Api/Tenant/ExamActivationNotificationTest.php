<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Enums\ExamAttemptStatus;
use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\Question;
use App\Models\Tenant\Subject;
use App\Models\Tenant\TeacherSubjectAssignment;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use App\Notifications\InAppNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamActivationNotificationTest extends TestCase
{
    protected Tenant $tenant;

    protected User $admin;

    protected User $teacher;

    protected User $student;

    protected Subject $subject;

    protected ClassLevel $classLevel;

    protected ClassArm $classArm;

    protected AcademicSession $academicSession;

    protected Term $term;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set("queue.default", "sync");

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $this->subject = Subject::create([
            "name" => "Mathematics",
            "code" => "MATH101",
        ]);

        $this->classLevel = ClassLevel::create([
            "name" => "Grade 10",
            "slug" => "grade-10",
        ]);

        $this->classArm = ClassArm::create([
            "name" => "Grade 10 A",
            "class_level_id" => $this->classLevel->id,
        ]);

        $this->academicSession = AcademicSession::create([
            "name" => "2025/2026",
            "start_date" => "2025-09-01",
            "end_date" => "2026-08-31",
            "is_current" => true,
        ]);

        $this->term = Term::create([
            "name" => "First Term",
            "academic_session_id" => $this->academicSession->id,
            "start_date" => "2025-09-01",
            "end_date" => "2025-12-20",
            "is_current" => true,
        ]);

        $this->admin = User::create([
            "first_name" => "Admin",
            "last_name" => "User",
            "email" => "admin@test.com",
            "password" => bcrypt("password"),
            "role" => RoleType::SchoolAdmin->value,
            "is_active" => true,
        ]);
        $this->admin->assignRole("school_admin");

        $this->teacher = User::create([
            "first_name" => "Teacher",
            "last_name" => "One",
            "email" => "teacher1@test.com",
            "password" => bcrypt("password"),
            "role" => RoleType::Teacher->value,
            "is_active" => true,
        ]);
        $this->teacher->assignRole("teacher");

        $this->student = User::create([
            "first_name" => "Student",
            "last_name" => "One",
            "email" => "student@test.com",
            "password" => bcrypt("password"),
            "role" => RoleType::Student->value,
            "is_active" => true,
        ]);
        $this->student->assignRole("student");

        $this->student->studentProfile()->create([
            "class_level_id" => $this->classLevel->id,
            "class_arm_id" => $this->classArm->id,
            "guardian_email" => "guardian@test.com",
            "admission_number" => "STU001",
        ]);

        TeacherSubjectAssignment::create([
            "user_id" => $this->teacher->id,
            "subject_id" => $this->subject->id,
            "class_level_id" => $this->classLevel->id,
            "class_arm_id" => $this->classArm->id,
            "academic_session_id" => $this->academicSession->id,
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
        Sanctum::actingAs($user, ["*"], "tenant");

        return $this;
    }

    protected function createDraftExam(): Exam
    {
        return Exam::create([
            "title" => "Test Exam",
            "subject_id" => $this->subject->id,
            "class_level_id" => $this->classLevel->id,
            "class_arm_id" => $this->classArm->id,
            "term_id" => $this->term->id,
            "type" => "exam",
            "status" => "draft",
            "duration_minutes" => 60,
            "pass_mark" => 50.0,
            "total_marks" => 0,
            "max_attempts" => 1,
            "scheduled_start" => now()->addDay(),
            "created_by" => $this->teacher->id,
            "settings" => ["require_attendance" => false],
        ]);
    }

    protected function addQuestionToExam(Exam $exam): Question
    {
        $question = Question::create([
            "content" => "What is 2+2?",
            "type" => "mcq",
            "default_marks" => 5,
            "subject_id" => $this->subject->id,
            "class_level_id" => $this->classLevel->id,
            "created_by" => $this->teacher->id,
            "is_active" => true,
            "academic_session_id" => $this->academicSession->id,
            "term_id" => $this->term->id,
        ]);

        $exam->examQuestions()->create([
            "question_id" => $question->id,
            "order" => $exam->examQuestions()->max("order") + 1,
            "marks" => 5.0,
        ]);

        $exam->update(["total_marks" => $exam->examQuestions()->sum("marks")]);

        return $question;
    }

    protected function createSubmittedExam(): Exam
    {
        $exam = $this->createDraftExam();

        for ($i = 0; $i < 10; $i++) {
            $this->addQuestionToExam($exam);
        }

        $this->actingAsTenant($this->teacher);
        $this->postJson(
            "/api/exams/{$exam->id}/submit-for-review",
        )->assertStatus(200);

        return $exam->fresh();
    }

    public function test_activation_sends_notification_to_students_with_attempts(): void
    {
        $exam = $this->createSubmittedExam();

        ExamAttempt::create([
            "exam_id" => $exam->id,
            "student_id" => $this->student->id,
            "attempt_number" => 1,
            "status" => ExamAttemptStatus::InProgress->value,
            "started_at" => now(),
        ]);

        Notification::fake();

        $this->actingAsTenant($this->admin);
        $response = $this->postJson("/api/exams/{$exam->id}/activate");

        $response->assertStatus(200);
        $response->assertJsonPath("data.status", "active");

        Notification::assertSentTo(
            [$this->student],
            InAppNotification::class,
            function (InAppNotification $notification, array $channels) use (
                $exam,
            ) {
                return $notification->title === "Exam Activated" &&
                    $notification->message ===
                        "The exam {$exam->title} is now active." &&
                    $notification->type === "success" &&
                    $notification->action["url"] ===
                        "/student/exams/{$exam->id}" &&
                    $notification->action["label"] === "View Exam";
            },
        );
    }

    public function test_notification_is_persisted_in_database_for_later_viewing(): void
    {
        $exam = $this->createSubmittedExam();

        ExamAttempt::create([
            "exam_id" => $exam->id,
            "student_id" => $this->student->id,
            "attempt_number" => 1,
            "status" => ExamAttemptStatus::InProgress->value,
            "started_at" => now(),
        ]);

        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/activate")->assertStatus(200);

        $this->student->refresh();

        // Assert the notification was actually stored in the DB
        $this->assertCount(1, $this->student->notifications);

        $notification = $this->student->notifications->first();

        $this->assertEquals(InAppNotification::class, $notification->type);
        $this->assertEquals("Exam Activated", $notification->data["title"]);
        $this->assertStringContainsString(
            $exam->title,
            $notification->data["message"],
        );
        $this->assertEquals("success", $notification->data["type"]);
        $this->assertEquals(
            "/student/exams/{$exam->id}",
            $notification->data["action"]["url"],
        );
        $this->assertEquals(
            "View Exam",
            $notification->data["action"]["label"],
        );

        // Verify it's unread initially — read-it-later semantics
        $this->assertNull($notification->read_at);
    }

    public function test_notification_can_be_marked_as_read(): void
    {
        $exam = $this->createSubmittedExam();

        ExamAttempt::create([
            "exam_id" => $exam->id,
            "student_id" => $this->student->id,
            "attempt_number" => 1,
            "status" => ExamAttemptStatus::InProgress->value,
            "started_at" => now(),
        ]);

        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/activate")->assertStatus(200);

        $this->student->refresh();

        // Mark as read — as the frontend would when user views it
        $this->student->notifications->first()->markAsRead();

        $this->assertNotNull(
            $this->student->notifications->first()->read_at,
            "read_at should be set after markAsRead()",
        );
    }

    public function test_multiple_students_each_get_their_own_notification(): void
    {
        $studentTwo = User::create([
            "first_name" => "Student",
            "last_name" => "Two",
            "email" => "student2@test.com",
            "password" => bcrypt("password"),
            "role" => RoleType::Student->value,
            "is_active" => true,
        ]);
        $studentTwo->assignRole("student");

        $exam = $this->createSubmittedExam();

        foreach ([$this->student, $studentTwo] as $student) {
            ExamAttempt::create([
                "exam_id" => $exam->id,
                "student_id" => $student->id,
                "attempt_number" => 1,
                "status" => ExamAttemptStatus::InProgress->value,
                "started_at" => now(),
            ]);
        }

        $this->actingAsTenant($this->admin);
        $this->postJson("/api/exams/{$exam->id}/activate")->assertStatus(200);

        $this->student->refresh();
        $studentTwo->refresh();

        // Each student should have their own notification row
        $this->assertCount(1, $this->student->notifications);
        $this->assertCount(1, $studentTwo->notifications);
        $this->assertEquals(
            $this->student->notifications->first()->data["title"],
            $studentTwo->notifications->first()->data["title"],
        );
    }

    public function test_notification_broadcasts_on_correct_private_channel(): void
    {
        $channel = $this->student->receivesBroadcastNotificationsOn();

        $this->assertSame(
            "tenant.{$this->student->tenant_id}.users.{$this->student->id}",
            $channel,
            "Broadcast channel must match routes/channels.php authorization",
        );
    }

    public function test_notification_broadcast_channel_matches_auth_pattern(): void
    {
        $channel = $this->student->receivesBroadcastNotificationsOn();

        $this->assertStringStartsWith("tenant.", $channel);
        $this->assertStringContainsString(".users.", $channel);
        $this->assertStringEndsWith(
            (string) $this->student->id,
            $channel,
        );
    }

    public function test_activation_does_not_send_notification_when_no_students(): void
    {
        $exam = $this->createSubmittedExam();

        $this->actingAsTenant($this->admin);
        $response = $this->postJson("/api/exams/{$exam->id}/activate");

        $response->assertStatus(200);
        $this->assertCount(0, $this->student->fresh()->notifications);
    }

    public function test_notification_is_queued(): void
    {
        $reflection = new \ReflectionClass(InAppNotification::class);

        $this->assertTrue(
            $reflection->implementsInterface(
                \Illuminate\Contracts\Queue\ShouldQueue::class,
            ),
            "InAppNotification must implement ShouldQueue so notifications are processed asynchronously.",
        );
    }
}
