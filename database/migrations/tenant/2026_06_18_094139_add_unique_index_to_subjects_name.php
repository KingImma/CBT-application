<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->deduplicateSubjects();

        Schema::table('subjects', function ($table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function ($table) {
            $table->dropUnique(['name']);
        });
    }

    private function deduplicateSubjects(): void
    {
        $duplicates = DB::table('subjects')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            $rows = DB::table('subjects')
                ->where('name', $dup->name)
                ->orderByDesc('is_active')
                ->orderBy('created_at')
                ->get();

            $keep = $rows->shift();

            foreach ($rows as $duplicate) {
                $this->reassignPivotRows('class_level_subject', 'class_level_id', $duplicate->id, $keep->id);
                $this->reassignPivotRows('class_arm_subject', 'class_arm_id', $duplicate->id, $keep->id);
                $this->reassignTeacherAssignments($duplicate->id, $keep->id);

                DB::table('subjects')->where('id', $duplicate->id)->delete();
            }
        }
    }

    private function reassignPivotRows(string $table, string $scopeColumn, string $fromId, string $toId): void
    {
        $foreignKey = $scopeColumn === 'class_level_id' ? 'class_level_id' : 'class_arm_id';

        $conflicting = DB::table($table)
            ->where('subject_id', $fromId)
            ->whereExists(function ($q) use ($table, $foreignKey, $toId) {
                $q->select(DB::raw(1))
                    ->from($table, 'existing')
                    ->whereColumn("existing.{$foreignKey}", "{$table}.{$foreignKey}")
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

    private function reassignTeacherAssignments(string $fromId, string $toId): void
    {
        DB::table('teacher_subject_assignments')
            ->where('subject_id', $fromId)
            ->update(['subject_id' => $toId]);
    }
};
