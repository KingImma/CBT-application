<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Input;

use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

class AddQuestionData extends Data
{
    public function __construct(
        #[Uuid, Exists('questions', 'id')]
        public readonly string $question_id,

        #[Nullable, Numeric, Min(0)]
        public readonly ?float $marks_override,
    ) {}
}
