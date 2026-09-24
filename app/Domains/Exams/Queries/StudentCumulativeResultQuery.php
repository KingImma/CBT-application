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
}
