<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateAssessmentData extends Data
{
    public function __construct(
        #[StringType, Max(255)]
        public readonly string|Optional $title,

        #[Numeric, Min(0)]
        public readonly float|Optional $total_marks,

        #[IntegerType, Min(1)]
        public readonly int|Optional|null $duration_minutes,

        #[StringType]
        public readonly string|Optional|null $description,
    ) {}
}
