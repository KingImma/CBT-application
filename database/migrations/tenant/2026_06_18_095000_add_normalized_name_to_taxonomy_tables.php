<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_levels', function ($table) {
            $table->string('normalized_name', 100)->nullable();
        });
        Schema::table('class_arms', function ($table) {
            $table->string('normalized_name', 50)->nullable();
        });
        Schema::table('subjects', function ($table) {
            $table->string('normalized_name', 100)->nullable();
        });

        DB::statement("UPDATE class_levels SET normalized_name = UPPER(TRIM(REGEXP_REPLACE(name, '\s+', ' ', 'g')))");
        DB::statement("UPDATE class_arms SET normalized_name = UPPER(TRIM(REGEXP_REPLACE(name, '\s+', ' ', 'g')))");
        DB::statement("UPDATE subjects SET normalized_name = UPPER(TRIM(REGEXP_REPLACE(name, '\s+', ' ', 'g')))");

        $this->deduplicateSubjects();

        DB::statement('ALTER TABLE class_levels ALTER COLUMN normalized_name SET NOT NULL');
        DB::statement('ALTER TABLE class_arms ALTER COLUMN normalized_name SET NOT NULL');
        DB::statement('ALTER TABLE subjects ALTER COLUMN normalized_name SET NOT NULL');

        DB::statement('DROP INDEX IF EXISTS uq_class_levels_name');
        DB::statement('DROP INDEX IF EXISTS uq_class_arms_name');
        DB::statement('DROP INDEX IF EXISTS subjects_name_unique');

        DB::statement('CREATE UNIQUE INDEX uq_class_levels_normalized_name ON class_levels (normalized_name) WHERE deleted_at IS NULL;');
        DB::statement('CREATE UNIQUE INDEX uq_class_arms_normalized_name ON class_arms (class_level_id, normalized_name) WHERE deleted_at IS NULL;');
        DB::statement('CREATE UNIQUE INDEX uq_subjects_normalized_name ON subjects (normalized_name);');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_class_levels_normalized_name');
        DB::statement('DROP INDEX IF EXISTS uq_class_arms_normalized_name');
        DB::statement('DROP INDEX IF EXISTS uq_subjects_normalized_name');

        DB::statement('CREATE UNIQUE INDEX uq_class_levels_name ON class_levels (name) WHERE deleted_at IS NULL;');
        DB::statement('CREATE UNIQUE INDEX uq_class_arms_name ON class_arms (class_level_id, name) WHERE deleted_at IS NULL;');

        Schema::table('class_levels', fn ($t) => $t->dropColumn('normalized_name'));
        Schema::table('class_arms', fn ($t) => $t->dropColumn('normalized_name'));
        Schema::table('subjects', fn ($t) => $t->dropColumn('normalized_name'));
    }

    private function deduplicateSubjects(): void
    {
        $duplicates = DB::table('subjects')
            ->select('normalized_name')
            ->groupBy('normalized_name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $rows = DB::table('subjects')
                ->where('normalized_name', $dup->normalized_name)
                ->orderByDesc('is_active')
                ->orderBy('created_at')
                ->get();

            $keep = $rows->shift();

            foreach ($rows as $duplicate) {
                $this->reassignSubjectPivot('class_level_subject', 'class_level_id', $duplicate->id, $keep->id);
                $this->reassignSubjectPivot('class_arm_subject', 'class_arm_id', $duplicate->id, $keep->id);

                DB::table('teacher_subject_assignments')
                    ->where('subject_id', $duplicate->id)
                    ->update(['subject_id' => $keep->id]);

                DB::table('subjects')->where('id', $duplicate->id)->delete();
            }
        }
    }

    private function reassignSubjectPivot(string $table, string $scopeColumn, string $fromId, string $toId): void
    {
        $conflicting = DB::table($table)
            ->where('subject_id', $fromId)
            ->whereExists(function ($q) use ($table, $scopeColumn, $toId) {
                $q->select(DB::raw(1))
                    ->from($table, 'existing')
                    ->whereColumn("existing.{$scopeColumn}", "{$table}.{$scopeColumn}")
                    ->where('existing.subject_id', $toId);
            })
            ->pluck('id');

        if ($conflicting->isNotEmpty()) {
            DB::table($table)->whereIn('id', $conflicting)->delete();
        }

        DB::table($table)
            ->where('subject_id', $fromId)
            ->update(['subject_id' => $toId]);
    }
};
