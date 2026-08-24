<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Uuid;
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

        #[Date]
        public readonly string $question_submission_ends,

        #[Nullable, Date]
        public readonly ?string $assessment_starts,

        #[Nullable, Date]
        public readonly ?string $assessment_ends,
    ) {}

    public static function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $data = $validator->getData();

            if (isset($data['assessment_starts'], $data['assessment_ends'])
                && $data['assessment_starts'] >= $data['assessment_ends']) {
                $validator->errors()->add('assessment_ends', 'The student end time must be after the student start time.');
            }
        });
    }
}
