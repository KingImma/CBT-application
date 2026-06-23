<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

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

        /** @var array<ResultQuestionData> */
        public readonly array $questions,
    ) {}
}
