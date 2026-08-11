<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class SubmissionQuestionOptionData extends Data
{
    public function __construct(
        #[StringType]
        public readonly string $content,

        #[BooleanType]
        public readonly bool $is_correct,

        #[Nullable, StringType, Max(10)]
        public readonly ?string $label,

        #[Nullable, StringType, Max(500)]
        public readonly ?string $image_url,
    ) {}
}
