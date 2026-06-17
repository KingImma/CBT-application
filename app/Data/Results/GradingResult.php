<?php

declare(strict_types=1);

namespace App\Data\Results;

class GradingResult
{
    public function __construct(
        private readonly string $questionId,
        private readonly bool $isCorrect,
        private readonly float $marksAwarded,
        private readonly string $questionType,
    ) {}

    public static function incorrect(string $questionId, string $questionType): self
    {
        return new self(
            questionId: $questionId,
            isCorrect: false,
            marksAwarded: 0.0,
            questionType: $questionType,
        );
    }

    public static function correct(string $questionId, float $marks, string $questionType): self
    {
        return new self(
            questionId: $questionId,
            isCorrect: true,
            marksAwarded: $marks,
            questionType: $questionType,
        );
    }

    public function getQuestionId(): string
    {
        return $this->questionId;
    }

    public function isCorrect(): bool
    {
        return $this->isCorrect;
    }

    public function getMarksAwarded(): float
    {
        return $this->marksAwarded;
    }

    public function getQuestionType(): string
    {
        return $this->questionType;
    }
}
