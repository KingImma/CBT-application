<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Output;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class AssessmentData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly ?float $total_marks,
        public readonly ?int $duration_minutes,

        #[WhenLoaded('creator')]
        public readonly mixed $creator,

        #[WhenLoaded('schedules')]
        public readonly mixed $schedules,
    ) {}
}
