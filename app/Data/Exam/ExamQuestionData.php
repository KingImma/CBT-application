<?php

declare(strict_types=1);

namespace App\Data\Exam;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

class ExamQuestionData extends Resource
{
    protected bool $showAnswers = true;

    public function showAnswers(bool $show): static
    {
        $this->showAnswers = $show;

        return $this;
    }

    public function __construct(
        public readonly string $id,
        public readonly string $exam_id,
        public readonly string $question_id,
        public readonly int $order,
        public readonly ?float $marks,
        #[WhenLoaded('question')]
        public readonly Optional $question,
    ) {}
}
