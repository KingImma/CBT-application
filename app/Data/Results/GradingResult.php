<?php

declare(strict_types=1);

namespace App\Data\Results;

class GradingResult
{
    public function __construct(
        public readonly string $questionId,
        public readonly bool   $isCorrect,
        public readonly float  $marksAwarded,
        public readonly string $questionType,
    ) {}

    public static function incorrect(string $questionId, string $questionType): self
    {
        return new self(
            questionId:   $questionId,
            isCorrect:    false,
            marksAwarded: 0.0,
            questionType: $questionType,
        );
    }

    public static function correct(string $questionId, float $marks, string $questionType): self
    {
        return new self(
            questionId:   $questionId,
            isCorrect:    true,
            marksAwarded: $marks,
            questionType: $questionType,
        );
    }
}
