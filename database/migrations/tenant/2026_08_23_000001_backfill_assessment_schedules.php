<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Corrective backfill upgrading every pre-"global assessment" database to the
 * occurrence model. Three generations are handled by one file:
 *
 *   A · Pre-refactor          (assessments.term_id exists)
 *       spawns schedules from dates/status, renames `submissions` ->
 *       `teacher_submissions`, repoints schedule_subjects.
 *   B · Post-refactor, pre-flip (no term_id, but assessments.class_level_id)
 *       papers/slots already correct; only the class-binding flip runs.
 *   Fresh                     (neither column) -> no-op.
 *
 * The shared "flip" moves class bindings off definitions onto occurrences:
 * assessments become school-wide ("End of Term Exam") while each schedule
 * carries its class level / arm ("-> JSS1 -> 1st-term schedule").
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessments', 'class_level_id')) {
            return; // fresh database — global definitions already in place
        }

        DB::transaction(function (): void {
            if (Schema::hasColumn('assessments', 'term_id')) {
                // Generation A: full structural upgrade first.
                $scheduleIdByAssessment = $this->backfillSchedules();

                $this->repointScheduleSubjects($scheduleIdByAssessment);
                $this->migrateSubmissionsViaRename($scheduleIdByAssessment);
                $this->dropLegacyLifecycleColumns();
            }

            $this->moveClassBindingsToSchedules();
        });
    }

    /**
     * One schedule per generation-A assessment. Status mapping:
     *   draft/open            -> draft + open window  (window opens at creation)
     *   submissions_closed    -> draft + closed window
     *   active/completed      -> active/completed + closed window
     * A missing legacy deadline defaults to now + 7 days. Class bindings are
     * copied later by moveClassBindingsToSchedules().
     *
     * @return array<string,string> assessment_id => assessment_schedule_id
     */
    private function backfillSchedules(): array
    {
        $mapping = [];

        DB::table('assessments')
            ->orderBy('id')
            ->chunkById(200, function ($assessments) use (&$mapping): void {
                foreach ($assessments as $assessment) {
                    $status = match ($assessment->status) {
                        'active' => ['assessment_status' => 'active', 'question_submission_status' => 'closed'],
                        'completed' => ['assessment_status' => 'completed', 'question_submission_status' => 'closed'],
                        'submissions_closed' => ['assessment_status' => 'draft', 'question_submission_status' => 'closed'],
                        default => ['assessment_status' => 'draft', 'question_submission_status' => 'open'],
                    };

                    $scheduleId = (string) Str::uuid();

                    $row = array_merge([
                        'id' => $scheduleId,
                        'assessment_id' => $assessment->id,
                        'academic_session_id' => DB::table('terms')->where('id', $assessment->term_id)->value('academic_session_id'),
                        'term_id' => $assessment->term_id,
                        'question_submission_ends' => $assessment->submission_closes_at ?? now()->addDays(7),
                        'assessment_starts' => $assessment->student_starts_at,
                        'assessment_ends' => $assessment->student_ends_at,
                        'activated_at' => $assessment->activated_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ], $status);

                    // Databases whose schedules table was born with NOT NULL
                    // class bindings take them straight from the definition;
                    // everywhere else moveClassBindingsToSchedules() copies
                    // them after the fact.
                    if (Schema::hasColumn('assessment_schedules', 'class_level_id')) {
                        $row['class_level_id'] = $assessment->class_level_id;
                        $row['class_arm_id'] = $assessment->class_arm_id;
                    }

                    DB::table('assessment_schedules')->insert($row);

                    $mapping[$assessment->id] = $scheduleId;
                }
            });

        return $mapping;
    }

    /** @param array<string,string> $scheduleIdByAssessment */
    private function repointScheduleSubjects(array $scheduleIdByAssessment): void
    {
        if (! Schema::hasTable('schedule_subjects')) {
            return;
        }

        Schema::table('schedule_subjects', function (Blueprint $table): void {
            $table->uuid('assessment_schedule_id')->nullable();
        });

        DB::table('schedule_subjects')
            ->select(['id', 'assessment_id'])
            ->orderBy('id')
            ->chunkById(200, function ($slots) use ($scheduleIdByAssessment): void {
                foreach ($slots as $slot) {
                    DB::table('schedule_subjects')
                        ->where('id', $slot->id)
                        ->update(['assessment_schedule_id' => $scheduleIdByAssessment[$slot->assessment_id]]);
                }
            });

        Schema::table('schedule_subjects', function (Blueprint $table): void {
            $table->dropForeign(['assessment_id']);
            $table->dropUnique(['assessment_id', 'subject_id']);
            $table->dropIndex(['assessment_id', 'starts_at', 'ends_at']);
            $table->dropColumn('assessment_id');
        });

        DB::statement('alter table schedule_subjects alter column assessment_schedule_id set not null');

        Schema::table('schedule_subjects', function (Blueprint $table): void {
            $table->foreign('assessment_schedule_id')
                ->references('id')->on('assessment_schedules')->cascadeOnDelete();
            $table->unique(['assessment_schedule_id', 'subject_id']);
            $table->index(['assessment_schedule_id', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Legacy `submissions` and current `teacher_submissions` are identical
     * except for the parent key, so papers move via rename — preserving UUIDs
     * while Postgres/InnoDB automatically retarget child FKs
     * (submission_questions, submission_comments).
     *
     * @param  array<string,string>  $scheduleIdByAssessment
     */
    private function migrateSubmissionsViaRename(array $scheduleIdByAssessment): void
    {
        Schema::dropIfExists('teacher_submissions'); // empty table from the sibling create-migration
        Schema::rename('submissions', 'teacher_submissions');

        Schema::table('teacher_submissions', function (Blueprint $table): void {
            $table->uuid('assessment_schedule_id')->nullable();
        });

        DB::table('teacher_submissions')
            ->select(['id', 'assessment_id'])
            ->orderBy('id')
            ->chunkById(200, function ($papers) use ($scheduleIdByAssessment): void {
                foreach ($papers as $paper) {
                    DB::table('teacher_submissions')
                        ->where('id', $paper->id)
                        ->update(['assessment_schedule_id' => $scheduleIdByAssessment[$paper->assessment_id]]);
                }
            });

        DB::statement('alter table teacher_submissions alter column assessment_schedule_id set not null');

        Schema::table('teacher_submissions', function (Blueprint $table): void {
            $table->foreign('assessment_schedule_id')
                ->references('id')->on('assessment_schedules')->cascadeOnDelete();
            // Constraints keep their pre-rename (`submissions_*`) names.
            $table->dropForeign('submissions_assessment_id_foreign');
            $table->dropUnique('submissions_assessment_id_teacher_id_subject_id_unique');
            $table->dropIndex('submissions_assessment_id_status_index');
            $table->dropColumn('assessment_id');
            $table->unique(['assessment_schedule_id', 'teacher_id', 'subject_id']);
            $table->index(['assessment_schedule_id', 'status']);
        });
    }

    /**
     * Generation A only: strip the legacy lifecycle columns from definitions.
     */
    private function dropLegacyLifecycleColumns(): void
    {
        Schema::table('assessments', function (Blueprint $table): void {
            $table->text('description')->nullable();
        });

        // `instructions` is renamed to `description` (decision #7).
        DB::table('assessments')->update(['description' => DB::raw('instructions')]);

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropIndex(['status', 'submission_closes_at']);
            $table->dropIndex(['status', 'student_starts_at']);
            $table->dropIndex(['status', 'student_ends_at']);
            $table->dropForeign(['term_id']);
            $table->dropColumn([
                'term_id',
                'status',
                'submission_opens_at',
                'submission_closes_at',
                'student_starts_at',
                'student_ends_at',
                'activated_at',
                'instructions',
            ]);
        });
    }

    /**
     * Shared flip (generations A + B): class bindings move from definitions
     * onto every schedule. Uniqueness widens to
     * (assessment_id, class_level_id, term_id).
     */
    private function moveClassBindingsToSchedules(): void
    {
        // Schedules born without class columns (generations A + B) get them
        // here; tables that already carry them only need the definition side
        // stripped.
        if (! Schema::hasColumn('assessment_schedules', 'class_level_id')) {
            Schema::table('assessment_schedules', function (Blueprint $table): void {
                $table->uuid('class_level_id')->nullable();
                $table->uuid('class_arm_id')->nullable();
            });

            DB::statement(
                'update assessment_schedules set '
                .'class_level_id = (select class_level_id from assessments where assessments.id = assessment_schedules.assessment_id), '
                .'class_arm_id = (select class_arm_id from assessments where assessments.id = assessment_schedules.assessment_id)'
            );

            DB::statement('alter table assessment_schedules alter column class_level_id set not null');

            Schema::table('assessment_schedules', function (Blueprint $table): void {
                $table->foreign('class_level_id')->references('id')->on('class_levels')->restrictOnDelete();
                $table->foreign('class_arm_id')->references('id')->on('class_arms')->nullOnDelete();
            });
        }

        if ($this->hasIndex('assessment_schedules_assessment_id_class_level_id_term_id_unique') === false) {
            Schema::table('assessment_schedules', function (Blueprint $table): void {
                $table->unique(['assessment_id', 'class_level_id', 'term_id']);
                $table->index(['class_level_id', 'assessment_status']);
            });
        }

        if ($this->hasIndex('assessment_schedules_assessment_id_term_id_unique')) {
            Schema::table('assessment_schedules', function (Blueprint $table): void {
                $table->dropUnique(['assessment_id', 'term_id']);
            });
        }

        Schema::table('assessments', function (Blueprint $table): void {
            $table->dropForeign(['class_level_id']);
            $table->dropForeign(['class_arm_id']);
            $table->dropIndex(['class_level_id', 'created_at']);
            $table->dropColumn(['class_level_id', 'class_arm_id']);
        });
    }

    /** Postgres indexes and unique constraints share a relname namespace. */
    private function hasIndex(string $name): bool
    {
        return (bool) DB::selectOne(
            'select 1 from pg_class where relname = ? and relkind in (?, ?)',
            [$name, 'i', 'I'],
        );
    }

    /**
     * Data backfills are not reversible; use migrate:fresh to reset instead.
     */
    public function down(): void {}
};
