<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Output;

use App\Enums\AssessmentStatus;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Optional;
use Spatie\LaravelData\Resource;

class AssessmentData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly AssessmentStatus $status,
        public readonly ?float $total_marks,
        public readonly ?int $duration_minutes,
        public readonly ?string $submission_opens_at,
        public readonly ?string $submission_closes_at,
        public readonly ?string $student_starts_at,
        public readonly ?string $student_ends_at,
        public readonly ?string $activated_at,
        public readonly ?string $instructions,

        #[Computed]
        public readonly Optional|int $submission_count,

        #[WhenLoaded('classLevel')]
        public readonly mixed $classLevel,

        #[WhenLoaded('classArm')]
        public readonly mixed $classArm,

        #[WhenLoaded('term')]
        public readonly mixed $term,

        #[WhenLoaded('creator')]
        public readonly mixed $creator,

        #[WhenLoaded('submissions')]
        public readonly mixed $submissions,
    ) {}
}
