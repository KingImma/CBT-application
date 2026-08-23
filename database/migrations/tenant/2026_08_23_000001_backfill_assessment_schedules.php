<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Corrective backfill for databases created before the AssessmentSchedule
 * refactor. Carries legacy rows into the occurrence model:
 *
 *   assessments (fat, term-scoped) ──> slim definitions + one AssessmentSchedule each
 *   submissions                   ──> teacher_submissions (renamed, repointed)
 *   schedule_subjects             ──> repointed to assessment_schedule_id
 *
 * No-ops on fresh databases (guarded on the legacy `assessments.term_id`
 * column), so both worlds share a single migration history. Assumes the two
 * 2026_08_10_000002 create-migrations have already run — they sort before
 * this file and create empty `assessment_schedules` / `teacher_submissions`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('assessments', 'term_id')) {
            return; // fresh database — new schema already in place
        }

        DB::transaction(function (): void {
            $scheduleIdByAssessment = $this->backfillSchedules();

            $this->repointScheduleSubjects($scheduleIdByAssessment);
            $this->migrateSubmissionsViaRename($scheduleIdByAssessment);
            $this->slimAssessments();
        });
    }

    /**
     * One schedule per legacy assessment. Status mapping:
     *   draft/open            -> draft + open window  (window opens at creation)
     *   submissions_closed    -> draft + closed window
     *   active/completed      -> active/completed + closed window
     * A missing legacy deadline defaults to now + 7 days.
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

                    DB::table('assessment_schedules')->insert(array_merge([
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
                    ], $status));

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

    private function slimAssessments(): void
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
     * Data backfills are not reversible; use migrate:fresh to reset instead.
     */
    public function down(): void {}
};
