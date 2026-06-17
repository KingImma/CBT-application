<?php

declare(strict_types=1);

namespace App\Data\Exam;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class ExamAttemptData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly int $attempt_number,
        public readonly ?float $total_score,
        public readonly ?float $percentage_score,
        public readonly ?string $started_at,
        public readonly ?string $submitted_at,
        public readonly ?int $time_spent_seconds,
        #[WhenLoaded('student')]
        public readonly mixed $student,
        #[WhenLoaded('exam')]
        public readonly mixed $exam,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAttemptNumber(): int
    {
        return $this->attempt_number;
    }

    public function getTotalScore(): ?float
    {
        return $this->total_score;
    }

    public function getPercentageScore(): ?float
    {
        return $this->percentage_score;
    }

    public function getStartedAt(): ?string
    {
        return $this->started_at;
    }

    public function getSubmittedAt(): ?string
    {
        return $this->submitted_at;
    }

    public function getTimeSpentSeconds(): ?int
    {
        return $this->time_spent_seconds;
    }

    public function getStudent(): mixed
    {
        return $this->student;
    }

    public function getExam(): mixed
    {
        return $this->exam;
    }
}
