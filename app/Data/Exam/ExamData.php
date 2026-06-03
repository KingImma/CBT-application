<?php

declare(strict_types=1);

namespace App\Data\Exam;

use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

class ExamData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $type,
        public readonly string $status,
        #[WhenLoaded('subject')]
        public readonly Optional $subject,
        #[WhenLoaded('classLevel')]
        public readonly Optional $classLevel,
        #[WhenLoaded('classArm')]
        public readonly Optional $classArm,
        #[WhenLoaded('term')]
        public readonly Optional $term,
        public readonly ?float $total_marks,
        public readonly ?float $pass_mark,
        public readonly int $duration_minutes,
        public readonly ?int $max_attempts,
        #[Computed]
        public readonly Optional|int $question_count,
        public readonly ?string $scheduled_start,
        public readonly ?string $scheduled_end,
        public readonly ?string $instructions,
        #[WhenLoaded('creator')]
        public readonly Optional $creator,
    ) {}
}
