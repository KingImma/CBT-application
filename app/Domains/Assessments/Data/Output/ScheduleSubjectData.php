<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Output;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class ScheduleSubjectData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $assessment_id,
        public readonly string $subject_id,
        public readonly string $starts_at,
        public readonly string $ends_at,
        public readonly ?int $duration_minutes,

        #[WhenLoaded('subject')]
        public readonly mixed $subject,
    ) {}
}