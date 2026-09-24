<?php

declare(strict_types=1);

namespace App\Domains\Exams\Queries;

use Illuminate\Support\Facades\DB;

final class BroadsheetQuery
{
    /**
     * @param  string[]  $caTypes
     * @return array[students: array<int, object>, subjects: <int, objects>]
     */
    public function execute(string $classLevelId, ?string $classArmId, string $termId, array $caTypes): array
    {
        $bindings = [
            'term_id' => $termId,
            'ca_types' => '{'.implode(',', $caTypes).'}',
            'class_level_id' => $classLevelId,
            'class_arm_id' => $classArmId,
        ];

        $students = DB::select(<<<'SQL'
            WITH subject_scores AS (
                SELECT 
                    sp.user_id AS student_id,
                    sp.class_level_id,
                    sp.class_arm_id,
                    er.subject_id,
                    SUM(er.total_score) FILTER (WHERE e.type = ANY(:ca_types)) AS ca_score,
                    SUM(er.total_score) FILTER (WHERE e.type = 'exam')         AS exam_score,
                    COALESCE(SUM(er.total_score) FILTER (WHERE e.type = ANY(:ca_types)), 0) 
                        + COALESCE(SUM(er.total_score) FILTER (WHERE e.type = 'exam'), 0) AS subject_total
                FROM student_profiles sp
                LEFT JOIN exam_results er
                    ON er.student_id = sp.user_id
                    AND er.term_id = :term_id
                LEFT JOIN exams e 
                    ON e.id = er.exam_id
                WHERE sp.class_level_id = :class_level_id
                    AND (:class_arm_id::uuid IS NULL OR sp.class_arm_id = :class_arm_id)
                GROUP BY sp.user_id, sp.class_level_id, sp.class_arm_id, er.subject_id
            ),
            student_totals AS (
                SELECT 
                    student_id,
                    class_level_id,
                    COALESCE(SUM(subject_total), 0) AS total_score,
                    COALESCE(AVG(subject_total), 0) AS average_score
                FROM subject_scores
                GROUP BY student_id, class_level_id
            ),
            ranked_students AS (
                SELECT
                    st.*,
                    RANK() OVER (PARTITION BY st.class_level_id ORDER BY st.total_score DESC) AS position
                FROM student_totals st
            )
            SELECT
                u.id AS student_id,
                sp.admission_number,
                u.first_name || ' ' || u.last_name AS full_name,
                rs.total_score,
                rs.average_score,
                rs.position,
                COALESCE(
                    jsonb_agg(
                        jsonb_build_object(
                            'subject_id', ss.subject_id,
                            'ca', ss.ca_score,
                            'exam', ss.exam_score,
                            'total', ss.subject_total
                        ) ORDER BY sub.name
                    ) FILTER (WHERE ss.subject_id IS NOT NULL),
                    '[]'::jsonb
                ) AS subjects
            FROM ranked_students rs
            JOIN users u ON u.id = rs.student_id
            JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN subject_scores ss ON ss.student_id = rs.student_id AND subject_id IS NOT NULL
            LEFT JOIN subjects sub ON sub.id = ss.subject_id
            GROUP BY u.id, sp.admission_number, u.first_name, u.last_name, rs.total_score, rs.average_score, rs.position
            ORDER BY rs.position, full_name
        SQL, $bindings);

        $subjects = DB::select(<<<'SQL'
            SELECT 
                sub.id AS subject_id,
                sub.name AS subject_name,
                AVG(ss.subject_total) AS class_average
            FROM student_profiles sp
            JOIN exam_results er ON er.student_id = sp.user_id AND er.term_id = :term_id
            JOIN exams e ON e.id = er.exam_id
            JOIN subjects sub ON sub.id = er.subject_id
            JOIN LATERAL (
                SELECT 
                    COALESCE(SUM(er2.total_score) FILTER (WHERE e2.type = ANY(:ca_types)), 0)
                    + COALESCE(SUM(er2.total_score) FILTER (WHERE e2.type = 'exam'), 0) AS subject_total
                FROM exam_results er2
                JOIN exams e2 ON e2.id = er2.exam_id
                WHERE er2.student_id = sp.user_id
                    AND er2.term_id = :term_id
                    AND er2.subject_id = sub.id
            ) ss ON TRUE
            WHERE sp.class_level_id = :class_level_id
                AND (:class_arm_id::uuid IS NULL OR sp.class_arm_id = :class_arm_id)
            GROUP BY sub.id, sub.name
            ORDER BY sub.name
        SQL, $bindings);

        return ['students' => $students, 'subjects' => $subjects];
    }
}
