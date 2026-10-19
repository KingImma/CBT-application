<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\Assessments\Support\BackfillAssessmentSchedules;
use App\Enums\RoleType;
use App\Models\Tenant;
use App\Models\Tenant\AcademicSession;
use App\Models\Tenant\ClassLevel;
use App\Models\Tenant\Term;
use App\Models\Tenant\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The corrective migration only runs once per tenant database. Tenants that
 * already recorded its EARLIER version (pre-flip) can never re-run it via
 * tenants:migrate, so `tenants:ensure-assessment-schedule-format` exists to
 * heal them. These tests simulate such a half-migrated tenant and prove the
 * command upgrades it idempotently — and no-ops on current databases.
 */
class BackfillAssessmentSchedulesCommandTest extends TestCase
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

        User::create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => RoleType::SchoolAdmin->value,
            'is_active' => true,
        ]);

        // ── Simulate the "v1-recorded" residue ────────────────────────────
        // Assessments still carry class bindings (old create-migrations),
        // while assessment_schedules predates the flip: no class columns and
        // the old per-term uniqueness instead of the per-class one.
        //
        // NOTE: deliberately NO assessments_class_level_id_created_at_index —
        // real drifted tenants (e.g. jay-international) lack it, which is
        // what forced the existence guards in the backfill service.
        Schema::table('assessments', function (Blueprint $table): void {
            $table->foreignUuid('class_level_id')->constrained('class_levels')->restrictOnDelete();
            $table->foreignUuid('class_arm_id')->nullable()->constrained('class_arms')->nullOnDelete();
        });

        Schema::table('assessment_schedules', function (Blueprint $table): void {
            $table->dropForeign(['class_level_id']);
            $table->dropForeign(['class_arm_id']);
            $table->dropUnique(['assessment_id', 'class_level_id', 'term_id']);
            $table->dropIndex(['class_level_id', 'assessment_status']);
            $table->dropColumn(['class_level_id', 'class_arm_id']);
        });
        Schema::table('assessment_schedules', function (Blueprint $table): void {
            $table->unique(['assessment_id', 'term_id']);
        });

        $this->seedRows();
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        $this->tenant->delete();
        parent::tearDown();
    }

    /**
     * The command initializes tenancy itself and ends it in its finally
     * block, so park the current context first and restore it after.
     *
     * @return array{0:int,1:string} exit code + output
     */
    private function runCommand(): array
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $exit = Artisan::call('tenants:ensure-assessment-schedule-format', [
            '--tenant' => $this->tenant->id,
        ]);
        $output = Artisan::output();

        tenancy()->initialize($this->tenant);

        return [$exit, $output];
    }

    private function seedRows(): void
    {
        $userId = DB::table('users')->value('id');

        $firstId = (string) Str::uuid();
        $secondId = (string) Str::uuid();

        foreach ([[$firstId, 'CA Test 1'], [$secondId, 'CA Test 2']] as [$id, $title]) {
            DB::table('assessments')->insert([
                'id' => $id,
                'title' => $title,
                'class_level_id' => $this->levelId,
                'created_by' => $userId,
                'total_marks' => 20,
                'duration_minutes' => 45,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('assessment_schedules')->insert([
                'id' => (string) Str::uuid(),
                'assessment_id' => $id,
                'academic_session_id' => $this->sessionId,
                'term_id' => $this->termId,
                'question_submission_ends' => now()->addWeek(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    #[Test]
    public function heals_a_v1_recorded_tenant_to_the_global_format(): void
    {
        [$exit, $output] = $this->runCommand();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('Upgraded', $output);

        // Schedules inherited their definition's class binding...
        $bound = DB::table('assessment_schedules')->where('class_level_id', $this->levelId)->count();
        $this->assertSame(2, $bound);
        $this->assertFalse(
            DB::table('assessment_schedules')->whereNull('class_level_id')->exists()
        );

        // ...and definitions were slimmed.
        $this->assertFalse(Schema::hasColumn('assessments', 'class_level_id'));
        $this->assertFalse(Schema::hasColumn('assessments', 'class_arm_id'));

        // Uniqueness swapped from per-term to per-class.
        $this->assertTrue($this->hasIndex('assessment_schedules_assessment_id_class_level_id_term_id_unique'));
        $this->assertTrue($this->hasIndex('assessment_schedules_class_level_id_assessment_status_index'));
        $this->assertFalse($this->hasIndex('assessment_schedules_assessment_id_term_id_unique'));

        // Data survived the reshape.
        $this->assertSame(2, DB::table('assessments')->count());
        $this->assertSame(2, DB::table('assessment_schedules')->count());
    }

    #[Test]
    public function is_a_no_op_on_an_already_current_tenant(): void
    {
        $this->runCommand();
        [, $secondRun] = $this->runCommand();

        $this->assertStringContainsString('Already on the global-assessment format.', $secondRun);
        $this->assertSame(2, DB::table('assessment_schedules')->count());
        $this->assertSame(2, DB::table('assessments')->count());
    }

    #[Test]
    public function reports_failure_and_leaves_the_tenant_untouched_when_upgrade_throws(): void
    {
        // Force a mid-upgrade failure: the flip needs the class_arms table.
        Schema::rename('class_arms', 'class_arms_shadow');

        [$exit, $output] = $this->runCommand();

        $this->assertSame(1, $exit, $output);
        $this->assertStringContainsString('✗ Failed', $output);

        // The transaction rolled back: definitions keep their bindings.
        $this->assertTrue(Schema::hasColumn('assessments', 'class_level_id'));

        Schema::rename('class_arms_shadow', 'class_arms');
    }

    /** @see BackfillAssessmentSchedules::hasIndex() */
    private function hasIndex(string $name): bool
    {
        return (bool) DB::selectOne(
            'select 1 from pg_class where relname = ? and relkind in (?, ?)',
            [$name, 'i', 'I'],
        );
    }
}
