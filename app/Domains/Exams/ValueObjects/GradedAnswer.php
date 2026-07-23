<?php

declare(strict_types=1);

namespace App\Domains\Exams\ValueObjects;

final class GradedAnswer
{
    private function __construct(
        public readonly string $answerId,
        public readonly bool $isCorrect,
        public readonly Marks $marksAwarded,
    ) {}

    /** Only way to build a correct answer — marks always > 0 by construction. */
    public static function correct(string $answerId, Marks $marksAwarded): self
    {
        return new self($answerId, true, $marksAwarded);
    }

    /** Only way to build an incorrect answer — marks always zero, never passed in. */
    public static function incorrect(string $answerId): self
    {
        return new self($answerId, false, Marks::zero());
    }
}
