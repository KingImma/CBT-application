<?php

declare(strict_types=1);

namespace App\Actions\Exam;

class ResolveGrade
{
    public static function execute(
        float $percentageScore,
        ?array $grades,
    ): ?string {
        if ($grades === null || $grades === []) {
            return null;
        }

        // Sort by min_score to ensure reliable iteration order
        $sorted = collect($grades)->sortBy("min_score")->values()->all();

        foreach ($sorted as $grade) {
            if (
                $percentageScore >= $grade["min_score"] &&
                $percentageScore <= $grade["max_score"]
            ) {
                return $grade["label"];
            }
        }

        // Fallback: return the lowest grade (covers gaps below 0 or above 100)
        return $sorted[0]["label"] ?? "Ungraded";
    }
}
