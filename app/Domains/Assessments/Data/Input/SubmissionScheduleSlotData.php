<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use App\Shared\Casts\ToUtc;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;

class SubmissionScheduleSlotData extends Data
{
    public function __construct(
        #[Date, WithCast(ToUtc::class)]
        public readonly string $starts_at,
        #[Date, WithCast(ToUtc::class)]
        public readonly string $ends_at,
        #[Nullable, IntegerType, Min(1)]
        public readonly ?int $duration_minutes = null,
    ) {
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $data = $validator->getData();

            $startsAt = ToUtc::normalize($data['starts_at'] ?? null);
            $endsAt = ToUtc::normalize($data['ends_at'] ?? null);

            if ($startsAt !== null && $endsAt !== null && $startsAt->greaterThanOrEqualTo($endsAt)) {
                $validator->errors()->add('ends_at', 'End time must be after start time.');
            }
        });
    }
}