<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

/**
 * A definition is school-wide: no class binding, no dates. Occurrences carry
 * both (CreateScheduleData).
 */
class CreateAssessmentData extends Data
{
    public function __construct(
        #[StringType, Max(255)]
        public readonly string $title,

        #[Numeric, Min(0)]
        public readonly float $total_marks,

        #[Nullable, IntegerType, Min(1)]
        public readonly ?int $duration_minutes,

        #[Nullable, StringType]
        public readonly ?string $description,
    ) {}
}
