<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class ExamQuestionData extends Resource
{
    public bool $showAnswers = true;

    public function __construct(
        public readonly string $id,
        public readonly string $exam_id,
        public readonly string $question_id,
        public readonly int $order,
        public readonly ?float $marks,
        public readonly bool $is_marks_locked,

        #[WhenLoaded('question')]
        public readonly mixed $question,
    ) {}

    public function hideAnswers(): static
    {
        $this->showAnswers = false;

        return $this;
    }
}
