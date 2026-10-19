<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Subject;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Light verification of the corrective backfill migration: rebuilds the
 * pre-refactor (legacy) state inside a live tenant database, runs the single
 * migration file, and asserts every mapping decision.
 */
class LegacyAssessmentBackfillTest extends TestCase
{
    protected Tenant $tenant;

    protected string $sessionId;

    protected string $termId;

    protected string $levelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        tenancy()->initialize($this->tenant);

        $session = AcademicSession::create([
            'name' => '2025/2026',
            'start_date' => '2025-09-01',
            'end_date' => '2026-08-31',
            'is_current' => true,
        ]);
        $this->sessionId = $session->id;

        $term = Term::create([
            'name' => 'First Term',
            'academic_session_id' => $session->id,
            'start_date' => '2025-09-01',
            'end_date' => '2025-12-20',
            'is_current' => true,
        ]);
        $this->termId = $term->id;

        $this->levelId = ClassLevel::create(['name' => 'Grade 10', 'slug' => 'grade-10'])->id;
        Subject::create(['name' => 'Mathematics', 'code' => 'MATH101']);
        $admin = User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);

        // ── Simulate the pre-refactor schema ──────────────────────────────
        Schema::table('assessments', function (Blueprint $table) use ($admin): void {
            // Legacy definitions were class-bound (the flip moves this).
            $table->foreignUuid('class_level_id')->constrained('class_levels')->restrictOnDelete();
            $table->foreignUuid('class_arm_id')->nullable()->constrained('class_arms')->nullOnDelete();
            $table->index(['class_level_id', 'created_at']);
            $table->foreignUuid('term_id')->constrained('terms')->restrictOnDelete();
            $table->string('status')->default('draft')->after('created_by');
            $table->timestamp('submission_opens_at')->nullable();
            $table->timestamp('submission_closes_at')->nullable();
            $table->timestamp('student_starts_at')->nullable();
            $table->timestamp('student_ends_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->text('instructions')->nullable();
            $table->index(['status', 'submission_closes_at']);
            $table->index(['status', 'student_starts_at']);
            $table->index(['status', 'student_ends_at']);
            unset($admin);
        });
        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropColumn('description');
        });

        Schema::rename('teacher_submissions', 'submissions');
        // Postgres retains the pre-rename constraint name.
        Schema::table('submissions', function (Blueprint $table): void {
            $table->dropForeign('teacher_submissions_assessment_schedule_id_foreign');
            $table->dropColumn('assessment_schedule_id');
        });
        Schema::table('submissions', function (Blueprint $table): void {
            $table->foreignUuid('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->unique(['assessment_id', 'teacher_id', 'subject_id']);
            $table->index(['assessment_id', 'status']);
        });

        Schema::table('schedule_subjects', function (Blueprint $table): void {
            $table->dropForeign(['assessment_schedule_id']);
            $table->dropUnique(['assessment_schedule_id', 'subject_id']);
            $table->dropIndex(['assessment_schedule_id', 'starts_at', 'ends_at']);
            $table->dropColumn('assessment_schedule_id');
        });
        Schema::table('schedule_subjects', function (Blueprint $table): void {
            $table->foreignUuid('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->unique(['assessment_id', 'subject_id']);
            $table->index(['assessment_id', 'starts_at', 'ends_at']);
        });

        $this->seedLegacyRows();
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    private function seedLegacyRows(): void
    {
        $levelId = DB::table('class_levels')->value('id');
        $userId = DB::table('users')->value('id');
        $subjectId = DB::table('subjects')->value('id');

        // open (dated) · active (dated, activated) · draft (null dates -> fallback)
        $openId = (string) Str::uuid();
        $activeId = (string) Str::uuid();
        $draftId = (string) Str::uuid();

        $base = fn (string $id, string $title): array => [
            'id' => $id,
            'title' => $title,
            'class_level_id' => $levelId,
            'class_arm_id' => null,
            'term_id' => $this->termId,
            'created_by' => $userId,
            'total_marks' => 100,
            'duration_minutes' => 90,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('assessments')->insert(array_merge($base($openId, 'Open one'), [
            'status' => 'open',
            'submission_closes_at' => '2026-09-07 17:00:00',
            'student_starts_at' => '2026-09-14 08:00:00',
            'student_ends_at' => '2026-09-14 10:00:00',
            'instructions' => 'Legacy instructions.',
        ]));

        DB::table('assessments')->insert(array_merge($base($activeId, 'Active one'), [
            'status' => 'active',
            'submission_closes_at' => '2026-09-05 12:00:00',
            'student_starts_at' => '2026-09-10 08:00:00',
            'student_ends_at' => '2026-09-10 15:00:00',
            'activated_at' => '2026-09-10 08:01:00',
        ]));

        DB::table('assessments')->insert(array_merge($base($draftId, 'Bare draft'), [
            'status' => 'draft',
        ]));

        DB::table('submissions')->insert([
            'id' => (string) Str::uuid(),
            'assessment_id' => $openId,
            'teacher_id' => $userId,
            'subject_id' => $subjectId,
            'title' => 'Maths Paper 1',
            'status' => 'approved',
            'total_marks' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('schedule_subjects')->insert([
            'id' => (string) Str::uuid(),
            'assessment_id' => $activeId,
            'subject_id' => $subjectId,
            'starts_at' => '2026-09-10 08:00:00',
            'ends_at' => '2026-09-10 09:30:00',
            'duration_minutes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function legacy_databases_are_upgraded_to_the_occurrence_model(): void
    {
        // tenancy()->initialize() already ran this file against the fresh
        // schema (the guard no-ops it). Revert that bookkeeping so the
        // migration executes for real against the simulated legacy state.
        DB::table('migrations')
            ->where('migration', '2026_08_23_000001_backfill_assessment_schedules')
            ->delete();

        $exit = Artisan::call('migrate', [
            '--path' => 'database/migrations/tenant/2026_08_23_000001_backfill_assessment_schedules.php',
            '--force' => true,
        ]);
        $output = Artisan::output();
        $this->assertSame(0, $exit, $output);
        // The file must actually have been selected by --path (not "Nothing to migrate").
        $this->assertStringContainsString('2026_08_23_000001', $output, $output);

        // Every legacy assessment gained exactly one schedule.
        $schedules = DB::table('assessment_schedules')->get()->keyBy('assessment_id');
        $this->assertCount(3, $schedules);

        [$open, $active, $draft] = ['Open one', 'Active one', 'Bare draft'];
        $byTitle = collect(DB::table('assessments')->get())->keyBy('title');

        // Session resolved server-side from the term; windows carried over.
        $openSchedule = $schedules[$byTitle[$open]->id];
        $this->assertSame('draft', $openSchedule->assessment_status);
        $this->assertSame('open', $openSchedule->question_submission_status);
        $this->assertSame((string) $this->sessionId, (string) $openSchedule->academic_session_id);
        $this->assertSame('2026-09-07 17:00:00', (string) $openSchedule->question_submission_ends);

        // The flip: schedules inherit their definition's class binding.
        $this->assertSame((string) $this->levelId, (string) $openSchedule->class_level_id);

        // Active maps to active/closed and keeps activated_at.
        $activeSchedule = $schedules[$byTitle[$active]->id];
        $this->assertSame('active', $activeSchedule->assessment_status);
        $this->assertSame('closed', $activeSchedule->question_submission_status);
        $this->assertSame('2026-09-10 08:01:00', (string) $activeSchedule->activated_at);

        // Null-dates draft falls back to a now+7d deadline.
        $draftSchedule = $schedules[$byTitle[$draft]->id];
        $deadline = Carbon::parse($draftSchedule->question_submission_ends);
        $this->assertTrue($deadline->between(
            now()->addDays(7)->subMinutes(5),
            now()->addDays(7)->addMinutes(5),
        ));

        // Papers moved by rename: UUID preserved, parent repointed.
        $paper = DB::table('teacher_submissions')->first();
        $this->assertNotNull($paper);
        $this->assertSame($paper->assessment_schedule_id, $openSchedule->id);
        $this->assertFalse(Schema::hasColumn('teacher_submissions', 'assessment_id'));

        // Slots repointed to their parent's schedule.
        $slot = DB::table('schedule_subjects')->first();
        $this->assertSame($slot->assessment_schedule_id, $activeSchedule->id);
        $this->assertFalse(Schema::hasColumn('schedule_subjects', 'assessment_id'));

        // Definitions slimmed: instructions renamed, lifecycle and class
        // columns dropped (the flip moved class bindings to schedules).
        $definition = $byTitle[$open];
        $this->assertSame('Legacy instructions.', $definition->description);
        foreach (['term_id', 'status', 'instructions', 'student_starts_at', 'submission_closes_at', 'class_level_id', 'class_arm_id'] as $gone) {
            $this->assertFalse(Schema::hasColumn('assessments', $gone));
        }
    }
}
