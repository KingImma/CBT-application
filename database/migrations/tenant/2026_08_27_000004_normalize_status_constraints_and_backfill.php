<?php

use App\Domains\Assessments\Support\BackfillSubmissionCompletion;
use App\Enums\AssessmentStatus;
use App\Enums\SubmissionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Heal enum CHECK constraints that drifted from PostgreSQL's default naming.
 *
 * PostgreSQL names enum checks `{table}_{column}_check` at creation time and
 * keeps that name across table renames, so production `teacher_submissions`
 * (formerly `submissions`) still enforces `submissions_status_check`. The
 * earlier `DROP ... IF EXISTS {default_name}` statements silently missed it
 * and ADD created a parallel constraint, so `completed` (and `published` on
 * assessment_schedules) are still rejected on drifted databases. Discover the
 * real names from pg_catalog and rebuild single-column checks from the current
 * enum cases, then run the submission backfill that 2026_08_27_000003 defers
 * to here. Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rebuild(
            'teacher_submissions',
            'status',
            'teacher_submissions_status_check',
            array_column(SubmissionStatus::cases(), 'value'),
        );

        $this->rebuild(
            'assessment_schedules',
            'assessment_status',
            'assessment_schedules_assessment_status_check',
            array_column(AssessmentStatus::cases(), 'value'),
        );

        $backfill = new BackfillSubmissionCompletion;

        if ($backfill->pendingCount() === 0) {
            return;
        }

        $backfill->upgrade();
    }

    /**
     * Not reversible; use migrate:fresh to reset instead.
     */
    public function down(): void {}

    /**
     * Drop every CHECK constraint that references a single column `column`
     * (whatever its legacy name) and re-add the canonical one with `values`.
     */
    private function rebuild(string $table, string $column, string $constraint, array $values): void
    {
        $allowed = collect($values)
            ->map(fn ($value) => "'{$value}'")
            ->implode(', ');

        foreach ($this->checkConstraintsOn($table, $column) as $name) {
            DB::statement(sprintf('ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s', $table, $name));
        }

        DB::statement(sprintf('ALTER TABLE %s ADD CONSTRAINT %s CHECK (%s IN (%s))', $table, $constraint, $column, $allowed));
    }

    /**
     * @return string[]
     */
    private function checkConstraintsOn(string $table, string $column): array
    {
        return array_map(
            static fn (object $row): string => $row->conname,
            DB::select(
                <<<'SQL'
                SELECT c.conname
                FROM pg_constraint c
                WHERE c.conrelid = ?::regclass
                  AND c.contype = 'c'
                  AND cardinality(c.conkey) = 1
                  AND c.conkey[1] = (
                      SELECT a.attnum
                      FROM pg_attribute a
                      WHERE a.attrelid = c.conrelid AND a.attname = ?
                  )
                SQL,
                [$table, $column],
            ),
        );
    }
};
