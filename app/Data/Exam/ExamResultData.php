<?php

declare(strict_types=1);

namespace App\Data\Exam;

use Spatie\LaravelData\Resource;

class ExamResultData extends Resource
{
    public function __construct(
        public readonly string $attempt_id,
        public readonly string $exam_id,
        public readonly string $exam_title,
        public readonly string $status,
        public readonly int $attempt_number,
        public readonly ?float $total_score,
        public readonly ?float $total_marks,
        public readonly ?float $percentage_score,
        public readonly ?string $grade,
        public readonly ?string $submitted_at,
        public readonly ?int $time_spent_seconds,
        public readonly array $questions,
    ) {}

    public function getAttemptId(): string
    {
        return $this->attempt_id;
    }

    public function getExamId(): string
    {
        return $this->exam_id;
    }

    public function getExamTitle(): string
    {
        return $this->exam_title;
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

    public function getTotalMarks(): ?float
    {
        return $this->total_marks;
    }

    public function getPercentageScore(): ?float
    {
        return $this->percentage_score;
    }

    public function getGrade(): ?string
    {
        return $this->grade;
    }

    public function getSubmittedAt(): ?string
    {
        return $this->submitted_at;
    }

    public function getTimeSpentSeconds(): ?int
    {
        return $this->time_spent_seconds;
    }

    public function getQuestions(): array
    {
        return $this->questions;
    }
}
