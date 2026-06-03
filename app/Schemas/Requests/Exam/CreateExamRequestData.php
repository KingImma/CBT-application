<?php

declare(strict_types=1);

namespace App\Schemas\Requests\Exam;

use App\Enums\ExamType;
use App\Schemas\Shared\ExamSettingsData;
use Spatie\LaravelData\Attributes\Validation\After;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\References\FieldReference;

/**
 * Request schema for creating a new exam.
 *
 * Each constructor property carries validation attributes that Spatie
 * Data reads at runtime to produce the Laravel validation rules array.
 * Nested objects (e.g. settings) are recursively resolved.
 */
class CreateExamRequestData extends Data
{
    public function __construct(
        #[StringType, Max(255)]
        public readonly string $title,

        #[Uuid, Exists('subjects', 'id')]
        public readonly string $subject_id,

        #[Uuid, Exists('class_levels', 'id')]
        public readonly string $class_level_id,

        #[Nullable, Uuid, Exists('class_arms', 'id')]
        public readonly ?string $class_arm_id,

        #[Uuid, Exists('terms', 'id')]
        public readonly string $term_id,

        public readonly ExamType $type,

        #[IntegerType, Min(1)]
        public readonly int $duration_minutes,

        #[Nullable, Numeric, Min(0)]
        public readonly ?float $pass_mark,

        #[Nullable, IntegerType, Min(1)]
        public readonly ?int $max_attempts,

        #[Nullable, Date]
        public readonly ?string $scheduled_start,

        #[Nullable, Date, After(new FieldReference('scheduled_start', fromRoot: true))]
        public readonly ?string $scheduled_end,

        #[Nullable, StringType]
        public readonly ?string $instructions,

        #[Nullable]
        public readonly ?ExamSettingsData $settings,
    ) {}
}
