<?php

declare(strict_types=1);

namespace App\Data\Exam;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class ExamQuestionData extends Resource
{
    private bool $showAnswers = true;

    public function showAnswers(bool $show): static
    {
        $this->showAnswers = $show;

        return $this;
    }

    public function isShowingAnswers(): bool
    {
        return $this->showAnswers;
    }

    public function __construct(
        public readonly string $id,
        public readonly string $exam_id,
        public readonly string $question_id,
        public readonly int $order,
        public readonly ?float $marks,
        #[WhenLoaded('question')]
        public readonly mixed $question,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getExamId(): string
    {
        return $this->exam_id;
    }

    public function getQuestionId(): string
    {
        return $this->question_id;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function getMarks(): ?float
    {
        return $this->marks;
    }

    public function getQuestion(): mixed
    {
        return $this->question;
    }
}
