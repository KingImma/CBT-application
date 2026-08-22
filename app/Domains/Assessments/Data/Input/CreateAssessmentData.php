<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

class CreateAssessmentData extends Data
{
    public function __construct(
        #[StringType, Max(255)]
        public readonly string $title,

        #[Uuid, Exists('class_levels', 'id')]
        public readonly string $class_level_id,

        #[Nullable, Uuid, Exists('class_arms', 'id')]
        public readonly ?string $class_arm_id,

        #[Numeric, Min(0)]
        public readonly float $total_marks,

        #[Nullable, IntegerType, Min(1)]
        public readonly ?int $duration_minutes,

        #[Nullable, StringType]
        public readonly ?string $description,
    ) {}
}
