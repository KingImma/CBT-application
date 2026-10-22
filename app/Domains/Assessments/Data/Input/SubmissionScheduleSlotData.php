<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;

class SubmissionScheduleSlotData extends Data
{
    public function __construct(
        #[Date]
        public readonly string $starts_at,
        #[Date]
        public readonly string $ends_at,
        #[Nullable, IntegerType, Min(1)]
        public readonly ?int $duration_minutes = null,
    ) {
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $data = $validator->getData();

            if (isset($data['starts_at'], $data['ends_at']) && $data['starts_at'] >= $data['ends_at']) {
                $validator->errors()->add('ends_at', 'End time must be after start time.');
            }
        });
    }
}