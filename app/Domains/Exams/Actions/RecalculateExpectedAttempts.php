<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions;

use App\Enums\ExamStatus;
use App\Enums\RoleType;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

/**
 * Recompute `exams.expected_attempts` for every active exam in a set of classes.
 *
 * The naive implementation counted active students once per exam, which turned
 * a single bulk import (or a class reassignment) into hundreds of `count(*)`
 * round trips. Here the student population is aggregated once with GROUP BY and
 * the exams are updated in bulk, so the cost is independent of the number of
 * active exams.
 */
class RecalculateExpectedAttempts
{
    /**
     * @param  iterable<array{0: ?string, 1: ?string}>  $pairs  [class_level_id, class_arm_id]
     */
    public function forClassArms(iterable $pairs): void
    {
        $levelIds = [];

        foreach ($pairs as $pair) {
            $levelId = $pair[0] ?? null;

            if ($levelId !== null && $levelId !== '') {
                $levelIds[(string) $levelId] = true;
            }
        }

        if ($levelIds === []) {
            return;
        }

        $levelIds = array_keys($levelIds);
        $connection = DB::connection('tenant');

        // One pass over the student population of the touched levels. The role
        // is read from the denormalised column so this does not need the
        // permission pivot table.
        $rows = $connection->table('student_profiles as sp')
            ->join('users as u', 'u.id', '=', 'sp.user_id')
            ->whereNull('u.deleted_at')
            ->where('u.is_active', true)
            ->where('u.role', RoleType::Student->value)
            ->whereIn('sp.class_level_id', $levelIds)
            ->groupBy('sp.class_level_id', 'sp.class_arm_id')
            ->selectRaw('sp.class_level_id as level_id, sp.class_arm_id as arm_id, COUNT(*) as total')
            ->get();

        $levelTotals = [];
        $armTotals = [];

        foreach ($rows as $row) {
            $levelId = (string) $row->level_id;
            $total = (int) $row->total;

            $levelTotals[$levelId] = ($levelTotals[$levelId] ?? 0) + $total;

            if ($row->arm_id !== null && $row->arm_id !== '') {
                $armId = (string) $row->arm_id;
                $armTotals[$levelId][$armId] = ($armTotals[$levelId][$armId] ?? 0) + $total;
            }
        }

        $exams = Exam::query()
            ->where('status', ExamStatus::Active->value)
            ->whereIn('class_level_id', $levelIds)
            ->get(['id', 'class_level_id', 'class_arm_id', 'expected_attempts']);

        /** @var array<int, list<string>> $updates */
        $updates = [];

        foreach ($exams as $exam) {
            $levelId = (string) $exam->class_level_id;
            $armId = $exam->class_arm_id;

            $expected = ($armId !== null && $armId !== '')
                ? ($armTotals[$levelId][(string) $armId] ?? 0)
                : ($levelTotals[$levelId] ?? 0);

            if ((int) $exam->expected_attempts === $expected) {
                continue;
            }

            $updates[$expected][] = (string) $exam->id;
        }

        if ($updates === []) {
            return;
        }

        $now = now();

        foreach ($updates as $expected => $ids) {
            foreach (array_chunk($ids, 500) as $chunk) {
                $connection->table('exams')
                    ->whereIn('id', $chunk)
                    ->update([
                        'expected_attempts' => $expected,
                        'updated_at' => $now,
                    ]);
            }
        }
    }
}
