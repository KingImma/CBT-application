<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Input;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class ExamSettingsData extends Data
{
    public function __construct(
        #[BooleanType]
        public readonly bool|Optional $randomize_questions,

        #[BooleanType]
        public readonly bool|Optional $show_result_immediately,

        #[Date]
        public readonly string|Optional $results_release_date,

        #[BooleanType]
        public readonly bool|Optional $require_attendance,

        #[IntegerType, Min(0)]
        public readonly int|Optional $max_suspicious_events,
    ) {}
}
