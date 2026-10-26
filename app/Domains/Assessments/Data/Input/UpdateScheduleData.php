<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use App\Shared\Casts\ToUtc;
use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateScheduleData extends Data
{
    public function __construct(
        #[Nullable, Uuid, Exists('class_levels', 'id')]
        public readonly string|Optional $class_level_id,
        #[Nullable, Uuid, Exists('class_arms', 'id')]
        public readonly string|Optional|null $class_arm_id,
        #[Date, WithCast(ToUtc::class)]
        public readonly string|Optional $question_submission_ends,
        #[Nullable, Date, WithCast(ToUtc::class)]
        public readonly string|Optional|null $assessment_starts,
        #[Nullable, Date, WithCast(ToUtc::class)]
        public readonly string|Optional|null $assessment_ends,
    ) {
    }

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $data = $validator->getData();

            $starts = ToUtc::normalize($data['assessment_starts'] ?? null);
            $ends = ToUtc::normalize($data['assessment_ends'] ?? null);

            if ($starts !== null && $ends !== null && $starts->greaterThanOrEqualTo($ends)) {
                    $validator->errors()->add('assessment_ends', 'The student end time must be after the student start time.');
                }
            });
        });
    }
}
