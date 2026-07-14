<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions;

class CalculateScore
{
    private const PERCENTAGE_MULTIPLIER = 100;

    public static function execute(float $score, float $totalMarks): float
    {
        if ($totalMarks <= 0) {
            return 0;
        }

        return ($score / $totalMarks) * self::PERCENTAGE_MULTIPLIER;
    }
}
