<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Data;

class ScheduleSubjectData extends Data
{
    public function __construct(
        #[Uuid, Exists('subjects', 'id')]
        public readonly string $subject_id,

        #[Date]
        public readonly string $starts_at,

        #[Date]
        public readonly string $ends_at,

        #[Nullable, IntegerType, Min(1)]
        public readonly ?int $duration_minutes,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $data = $validator->getData();

            if (isset($data['starts_at'], $data['ends_at']) && $data['starts_at'] >= $data['ends_at']) {
                $validator->errors()->add('ends_at', 'End time must be after start time.');
            }
        });
    }
}