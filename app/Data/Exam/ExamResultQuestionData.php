<?php

declare(strict_types=1);

namespace App\Data\Exam;

use Spatie\LaravelData\Resource;

class ExamResultQuestionData extends Resource
{
    public function __construct(
        public readonly string $question_id,
        public readonly string $content,
        public readonly ?string $image_url,
        public readonly float $marks_available,
        public readonly float $marks_awarded,
        public readonly bool $is_correct,
        public readonly array $options,
        public readonly array $selected_options,
    ) {}

    public function getQuestionId(): string
    {
        return $this->question_id;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function getMarksAvailable(): float
    {
        return $this->marks_available;
    }

    public function getMarksAwarded(): float
    {
        return $this->marks_awarded;
    }

    public function isCorrect(): bool
    {
        return $this->is_correct;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getSelectedOptions(): array
    {
        return $this->selected_options;
    }
}
