<?php

declare(strict_types=1);

namespace App\Domains\Exams\Queries;

use Illuminate\Support\Facades\DB;

final class StudentCumulativeResultQuery
{
    /**
     * One row per subject the student's class level offers; `terms` is a
     * jsonb map keyed by term_id, present only for terms with a result.
     *
     * @param  string[]  $termIds  the session's term ids
     * @param  string[]  $caTypes  exam types folded into "ca" (from ResolveCAComponentTypes)
     * @return array<int, object>
     */
    public function execute(string $studentId, string $classLevelId, array $termIds, array $caTypes): array
    {
        return DB::select(<<<'SQL'
            SELECT
                sub.id   AS subject_id,
                sub.name AS subject_name,
                COALESCE(
                    jsonb_object_agg(t.id, jsonb_build_object(
                        'ca',    scores.ca_score,
                        'exam',  scores.exam_score,
                        'total', COALESCE(scores.ca_score, 0) + COALESCE(scores.exam_score, 0)
                    )) FILTER (WHERE scores.ca_score IS NOT NULL OR scores.exam_score IS NOT NULL),
                    '{}'::jsonb
                ) AS terms
            FROM subjects sub
            JOIN class_level_subject cls
                ON cls.subject_id = sub.id AND cls.class_level_id = :class_level_id
            CROSS JOIN unnest(:term_ids::uuid[]) AS t(id)
            LEFT JOIN LATERAL (
                SELECT
                    SUM(er.total_score) FILTER (WHERE e.type = ANY(:ca_types)) AS ca_score,
                    SUM(er.total_score) FILTER (WHERE e.type = 'exam')         AS exam_score
                FROM exam_results er
                JOIN exams e ON e.id = er.exam_id
                WHERE er.student_id = :student_id
                    AND er.subject_id = sub.id
                    AND er.term_id = t.id
            ) scores ON TRUE
            GROUP BY sub.id, sub.name
            ORDER BY sub.name
        SQL, [
            'class_level_id' => $classLevelId,
            'term_ids' => '{'.implode(',', $termIds) . '}',
            'ca_types' => '{'.implode(',', $caTypes) . '}',
            'student_id' => $studentId,
        ]);
    }

    /**
     * Where the student sits in their cohort for the given term range, ranked
     * on the summed total score (same scoring rules as `execute`).
     *
     * @param  string[]  $termIds
     * @param  string[]  $caTypes
     * @return array{position: int, number_in_class: int}
     */
    public function classRank(
        string $studentId,
        string $classLevelId,
        ?string $classArmId,
        array $termIds,
        array $caTypes,
    ): array {
        if ($termIds === []) {
            return ['position' => 0, 'number_in_class' => 0];
        }

        $row = DB::selectOne(<<<'SQL'
            WITH scoped_scores AS (
                SELECT er.student_id, SUM(er.total_score) AS total_score
                FROM exam_results er
                JOIN exams e ON e.id = er.exam_id
                WHERE er.term_id = ANY(:term_ids::uuid[])
                    AND er.subject_id IN (
                        SELECT cls.subject_id
                        FROM class_level_subject cls
                        WHERE cls.class_level_id = :class_level_id
                    )
                    AND (e.type = ANY(:ca_types) OR e.type = 'exam')
                GROUP BY er.student_id
            ),
            cohort AS (
                SELECT
                    sp.user_id AS student_id,
                    COALESCE(ss.total_score, 0) AS total_score
                FROM student_profiles sp
                LEFT JOIN scoped_scores ss ON ss.student_id = sp.user_id
                WHERE sp.class_level_id = :class_level_id
                    AND (:class_arm_id::uuid IS NULL OR sp.class_arm_id = :class_arm_id)
            ),
            ranked AS (
                SELECT student_id, RANK() OVER (ORDER BY total_score DESC) AS position
                FROM cohort
            )
            SELECT
                (SELECT COUNT(*) FROM cohort) AS number_in_class,
                (SELECT position FROM ranked WHERE student_id = :student_id) AS position
        SQL, [
            'term_ids' => '{'.implode(',', $termIds) . '}',
            'ca_types' => '{'.implode(',', $caTypes) . '}',
            'class_level_id' => $classLevelId,
            'class_arm_id' => $classArmId,
            'student_id' => $studentId,
        ]);

        return [
            'position' => (int) ($row->position ?? 0),
            'number_in_class' => (int) ($row->number_in_class ?? 0),
        ];
    }
}