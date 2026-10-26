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

/**
 * An occurrence binds the global definition to a class level (and optionally
 * an arm) in a term: "End of Term Exam -> JSS1 -> 1st-term schedule".
 */
class CreateScheduleData extends Data
{
    public function __construct(
        #[Uuid, Exists('class_levels', 'id')]
        public readonly string $class_level_id,
        #[Nullable, Uuid, Exists('class_arms', 'id')]
        public readonly ?string $class_arm_id,
        #[Date, WithCast(ToUtc::class)]
        public readonly string $question_submission_ends,
        #[Nullable, Date, WithCast(ToUtc::class)]
        public readonly ?string $assessment_starts,
        #[Nullable, Date, WithCast(ToUtc::class)]
        public readonly ?string $assessment_ends,
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
