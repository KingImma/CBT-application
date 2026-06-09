<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Tenant;

use App\Console\Commands\CompleteExpiredExams;
use App\Enums\ExamStatus;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassArm;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Exam;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExamAutoCompleteMigrationTest extends TestCase
{
    protected Tenant $tenant;

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

        $this->subject = Subject::create([
            'name' => 'Mathematics',
            'code' => 'MATH101',
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

        $this->teacher = User::create([
            'first_name' => 'Teacher',
            'last_name' => 'One',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
            'role' => 'teacher',
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

    protected function actingAsTenant(User $user): static
    {
        Sanctum::actingAs($user, ['*'], 'tenant');

        return $this;
    }

    #[Test]
    public function backfill_completes_exams_with_expired_window(): void
    {
        $this->actingAsTenant($this->teacher);

        $exam = Exam::create([
            'title' => 'Expired Window Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => ExamStatus::Active,
            'duration_minutes' => 60,
            'pass_mark' => 50.00,
            'total_marks' => 100,
            'max_attempts' => 1,
            'scheduled_start' => now()->subDays(2),
            'window_end' => now()->subDay(),
            'expected_attempts' => 5,
            'completed_attempts' => 2,
            'created_by' => $this->teacher->id,
            'settings' => ['require_attendance' => false],
        ]);

        // Run the CompleteExpiredExams command which contains the backfill logic
        Artisan::call(CompleteExpiredExams::class);

        $this->assertEquals(ExamStatus::Completed, $exam->fresh()->status);
    }

    #[Test]
    public function backfill_completes_exams_with_max_attempts_reached(): void
    {
        $this->actingAsTenant($this->teacher);

        $exam = Exam::create([
            'title' => 'Max Attempts Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => ExamStatus::Active,
            'duration_minutes' => 60,
            'pass_mark' => 50.00,
            'total_marks' => 100,
            'max_attempts' => 3,
            'scheduled_start' => now()->subWeek(),
            'window_end' => now()->addDay(),
            'expected_attempts' => 10,
            'completed_attempts' => 10,
            'created_by' => $this->teacher->id,
            'settings' => ['require_attendance' => false],
        ]);

        // Run the CompleteExpiredExams command
        Artisan::call(CompleteExpiredExams::class);

        $this->assertEquals(ExamStatus::Completed, $exam->fresh()->status);
    }

    #[Test]
    public function backfill_does_not_affect_active_exams_with_open_window(): void
    {
        $this->actingAsTenant($this->teacher);

        $exam = Exam::create([
            'title' => 'Still Active Exam',
            'subject_id' => $this->subject->id,
            'class_level_id' => $this->classLevel->id,
            'class_arm_id' => $this->classArm->id,
            'term_id' => $this->term->id,
            'type' => 'exam',
            'status' => ExamStatus::Active,
            'duration_minutes' => 60,
            'pass_mark' => 50.00,
            'total_marks' => 100,
            'max_attempts' => 3,
            'scheduled_start' => now(),
            'window_end' => now()->addDay(),
            'expected_attempts' => 10,
            'completed_attempts' => 0,
            'created_by' => $this->teacher->id,
            'settings' => ['require_attendance' => false],
        ]);

        // Run the CompleteExpiredExams command
        Artisan::call(CompleteExpiredExams::class);

        $this->assertEquals(ExamStatus::Active, $exam->fresh()->status);
    }
}
