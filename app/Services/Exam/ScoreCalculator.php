<?php

declare(strict_types=1);

namespace App\Services\Exam;

/**
 * Pure functions for exam score computation.
 *
 * All methods are referentially transparent — same inputs always
 * produce the same output with no side effects.
 */
class ScoreCalculator
{
    private const PERCENTAGE_MULTIPLIER = 100;

    /**
     * Calculate the percentage score from raw score and total marks.
     *
     * @param  float  $score  The student's raw score.
     * @param  float  $totalMarks  The maximum possible marks.
     * @return float The percentage (0–100), or 0 if total marks is zero or negative.
     */
    public static function percentage(float $score, float $totalMarks): float
    {
        if ($totalMarks <= 0) {
            return 0;
        }

        return ($score / $totalMarks) * self::PERCENTAGE_MULTIPLIER;
    }
}
