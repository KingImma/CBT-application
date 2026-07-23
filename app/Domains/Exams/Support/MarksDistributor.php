<?php

declare(strict_types=1);

namespace App\Domains\Exams\Support;

use InvalidArgumentException;

final class MarksDistributor
{
    /**
     * Distribute marks across questions as evenly as possible, to two-decimal
     * precision (matching the decimal(5,2) marks column). Arithmetic is done in
     * integer hundredths to avoid float drift, and any leftover hundredths are
     * added one at a time to the questions at the beginning.
     *
     * @return list<float>
     *
     * @throws InvalidArgumentException
     */
    public function distribute(float $totalMarks, int $questionCount): array
    {
        if ($totalMarks < 0) {
            throw new InvalidArgumentException('Total marks cannot be negative.');
        }

        if ($questionCount <= 0) {
            throw new InvalidArgumentException('Questions number must be greater than 0');
        }

        $totalHundredths = (int) round($totalMarks * 100);

        $baseHundredthsPerQuestion = intdiv($totalHundredths, $questionCount);

        $remainingHundredthsToDistribute = $totalHundredths % $questionCount;

        $hundredthsPerQuestion = array_fill(
            0,
            $questionCount,
            $baseHundredthsPerQuestion,
        );

        for (
            $questionIndex = 0;
            $questionIndex < $remainingHundredthsToDistribute;
            $questionIndex++
        ) {
            $hundredthsPerQuestion[$questionIndex]++;
        }

        return array_map(
            static fn (int $hundredths): float => round($hundredths / 100, 2),
            $hundredthsPerQuestion,
        );
    }
}
