<?php

declare(strict_types=1);

namespace App\Data\Exam;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

class ExamAttemptData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly int $attempt_number,
        public readonly ?float $total_score,
        public readonly ?float $percentage_score,
        public readonly ?float $objective_score,
        public readonly ?float $theory_score,
        public readonly ?string $started_at,
        public readonly ?string $submitted_at,
        public readonly ?int $time_spent_seconds,
        #[WhenLoaded('student')]
        public readonly Optional|array $student,
        #[WhenLoaded('exam')]
        public readonly Optional|array $exam,
    ) {}
}
