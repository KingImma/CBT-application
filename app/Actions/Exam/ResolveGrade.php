<?php

declare(strict_types=1);

namespace App\Actions\Exam;

class ResolveGrade
{
    private const string UNGRADED = 'N/A';

    public static function execute(
        float $percentageScore,
        ?array $grades,
    ): string {
        if ($grades === null || $grades === []) {
            return self::UNGRADED;
        }

        $sorted = collect($grades)->sortBy('min_score')->values()->all();

        foreach ($sorted as $grade) {
            if (
                $percentageScore >= $grade['min_score'] &&
                $percentageScore <= $grade['max_score']
            ) {
                return $grade['label'];
            }
        }

        return $sorted[0]['label'] ?? self::UNGRADED;
    }
}
