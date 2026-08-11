<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use App\Enums\SubmissionQuestionType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Optional;

class SubmissionQuestionData extends Data
{
    public function __construct(
        public readonly SubmissionQuestionType $type,

        #[StringType]
        public readonly string $content,

        #[Numeric, Min(0)]
        public readonly float $marks,

        #[Nullable, StringType]
        public readonly ?string $explanation,

        #[Nullable, StringType, Max(500)]
        public readonly ?string $image_url,

        /** @var DataCollection<int, SubmissionQuestionOptionData>|Optional */
        #[ArrayType]
        #[DataCollectionOf(SubmissionQuestionOptionData::class)]
        public readonly DataCollection|Optional $options,
    ) {}
}
