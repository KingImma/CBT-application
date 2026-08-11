<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateSubmissionData extends Data
{
    public function __construct(
        #[StringType, Max(255)]
        public readonly string|Optional $title,

        #[StringType]
        public readonly string|Optional|null $description,
    ) {}
}
