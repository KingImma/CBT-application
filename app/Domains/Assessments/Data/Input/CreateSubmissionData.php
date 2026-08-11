<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Data\Input;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

class CreateSubmissionData extends Data
{
    public function __construct(
        #[StringType, Max(255)]
        public readonly string $title,

        #[Nullable, StringType]
        public readonly ?string $description,

        #[Uuid, Exists('subjects', 'id')]
        public readonly string $subject_id,
    ) {}
}
