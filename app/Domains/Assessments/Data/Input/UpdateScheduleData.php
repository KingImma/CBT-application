<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use Illuminate\Validation\Validator;
use Spatie\LaravelData\Attributes\Validation\Date;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateScheduleData extends Data
{
    public function __construct(
        #[Date]
        public readonly string|Optional $question_submission_ends,

        #[Nullable, Date]
        public readonly string|Optional|null $assessment_starts,

        #[Nullable, Date]
        public readonly string|Optional|null $assessment_ends,
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
