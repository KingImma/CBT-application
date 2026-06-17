<?php

declare(strict_types=1);

namespace App\Data\Exam;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

class ExamData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ExamType $type,
        public readonly ExamStatus $status,
        #[WhenLoaded('subject')] public readonly mixed $subject,
        #[WhenLoaded('classLevel')] public readonly mixed $classLevel,
        #[WhenLoaded('classArm')] public readonly mixed $classArm,
        #[WhenLoaded('term')] public readonly mixed $term,
        public readonly ?float $total_marks,
        public readonly ?float $pass_mark,
        public readonly int $duration_minutes,
        public readonly ?int $max_attempts,
        #[Computed] public readonly Optional|int $question_count,
        public readonly int $expected_attempts,
        public readonly int $completed_attempts,
        public readonly ?string $scheduled_start,
        public readonly ?string $instructions,
        public readonly ?string $published_at,
        public readonly bool $is_published,
        #[WhenLoaded('creator')] public readonly mixed $creator,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): ExamType
    {
        return $this->type;
    }

    public function getStatus(): ExamStatus
    {
        return $this->status;
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }

    public function getClassLevel(): mixed
    {
        return $this->classLevel;
    }

    public function getClassArm(): mixed
    {
        return $this->classArm;
    }

    public function getTerm(): mixed
    {
        return $this->term;
    }

    public function getTotalMarks(): ?float
    {
        return $this->total_marks;
    }

    public function getPassMark(): ?float
    {
        return $this->pass_mark;
    }

    public function getDurationMinutes(): int
    {
        return $this->duration_minutes;
    }

    public function getMaxAttempts(): ?int
    {
        return $this->max_attempts;
    }

    public function getQuestionCount(): Optional|int
    {
        return $this->question_count;
    }

    public function getExpectedAttempts(): int
    {
        return $this->expected_attempts;
    }

    public function getCompletedAttempts(): int
    {
        return $this->completed_attempts;
    }

    public function getScheduledStart(): ?string
    {
        return $this->scheduled_start;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function getPublishedAt(): ?string
    {
        return $this->published_at;
    }

    public function isPublished(): bool
    {
        return $this->is_published;
    }

    public function getCreator(): mixed
    {
        return $this->creator;
    }
}
