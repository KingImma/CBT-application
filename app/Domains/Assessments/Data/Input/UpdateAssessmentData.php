<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateAssessmentData extends Data
{
    public function __construct(
        #[StringType, Max(255)]
        public readonly string|Optional $title,

        #[Uuid, Exists('class_levels', 'id')]
        public readonly string|Optional $class_level_id,

        #[Uuid, Exists('class_arms', 'id')]
        public readonly string|Optional|null $class_arm_id,

        #[Uuid, Exists('terms', 'id')]
        public readonly string|Optional $term_id,

        #[Numeric, Min(0)]
        public readonly float|Optional $total_marks,

        #[IntegerType, Min(1)]
        public readonly int|Optional|null $duration_minutes,

        #[Date]
        public readonly string|Optional|null $submission_opens_at,

        #[Date]
        public readonly string|Optional|null $submission_closes_at,

        #[Date]
        public readonly string|Optional|null $student_starts_at,

        #[Date]
        public readonly string|Optional|null $student_ends_at,

        #[StringType]
        public readonly string|Optional|null $instructions,
    ) {}
}
