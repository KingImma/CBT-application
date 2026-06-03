<?php

declare(strict_types=1);

namespace App\Schemas\Shared;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * Nested settings object embedded in exam create/update payloads.
 *
 * Mirrors the shape stored as a JSON column on the exams table, but
 * also serves as the validation and transformation layer for API
 * request payloads that contain a `settings` key.
 */
class ExamSettingsData extends Data
{
    public function __construct(
        #[Nullable, BooleanType]
        public Optional|bool $randomize_questions,

        #[Nullable, BooleanType]
        public Optional|bool $show_result_immediately,

        #[Nullable, Date]
        public Optional|string|null $results_release_date,

        #[Nullable, BooleanType]
        public Optional|bool $require_attendance,

        #[Nullable, IntegerType, Min(0)]
        public Optional|int $max_suspicious_events,
    ) {}
}
