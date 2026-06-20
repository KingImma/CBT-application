<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

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
}