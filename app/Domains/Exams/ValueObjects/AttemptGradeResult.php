<?php

declare(strict_types=1);

namespace App\Domains\Exams\ValueObjects;

use App\Domains\Exams\ValueObjects\Marks;
use App\Enums\ExamAttemptStatus;
use App\Enums\PassOutcome;
use InvalidArgumentException;

final class AttemptGradeResult
{
    private function __construct(
        public readonly Marks $totalScore,
        public readonly Percentage $percentageScore,
        public readonly string $letterGrade,
        public readonly PassOutcome $passOutcome,
        public readonly int $timeSpentSeconds,
    ) {}

    public static function compute(
        Marks $totalScore,
        Percentage $percentage,
        string $letterGrade,
        ?float $passMark,
        int $timeSpentSeconds,
    ): self {
        if ($timeSpentSeconds < 0) {
            throw new InvalidArgumentException('time cannot be negative.');
        }

        return new self(
            totalScore: $totalScore,
            percentageScore: $percentage,
            letterGrade: $letterGrade,
            passOutcome: PassOutcome::resolve($percentage, $passMark),
            timeSpentSeconds: $timeSpentSeconds,
        );
    }

    private function baseAttributes(): array
    {
        return [
            'total_score' => $this->totalScore->value,
            'percentage_score' => $this->percentageScore->value,
            'grade' => $this->letterGrade,
        ];
    }

    public function toAttemptAttributes(): array
    {
        return [
            'status' => ExamAttemptStatus::Graded->value,
            ...$this->baseAttributes(),
            'time_spent_seconds' => $this->timeSpentSeconds,
        ];
    }

    public function toResultAttributes(): array
    {
        return [
            ...$this->baseAttributes(),
            'passed' => $this->passOutcome->toNullableBool(),
            'graded_at' => now(),
        ];
    }
}
