<?php

declare(strict_types=1);

namespace App\Services\Exam;

/**
 * Pure function for grade resolution.
 *
 * Given a percentage score and an array of grade boundary definitions,
 * returns the matching grade label. No database access — callers are
 * responsible for fetching the grading scale.
 */
class GradeResolver
{
    /**
     * Resolve a grade label from a percentage score against a grading scale.
     *
     * @param  float  $percentageScore  The student's percentage score (0–100).
     * @param  array<int, array{min_score: float, max_score: float, label: string}>|null  $grades  The grade boundaries from a GradingScale model.
     * @return string|null The grade label, or null if no match or no scale provided.
     */
    public static function resolve(float $percentageScore, ?array $grades): ?string
    {
        if ($grades === null || $grades === []) {
            return null;
        }

        foreach ($grades as $grade) {
            if ($percentageScore >= $grade['min_score'] && $percentageScore <= $grade['max_score']) {
                return $grade['label'];
            }
        }

        return null;
    }
}
