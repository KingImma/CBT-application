<?php

declare(strict_types=1);

namespace App\Actions\Exam;

class ResolveGrade
{
    public static function execute(float $percentageScore, ?array $grades): ?string
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
