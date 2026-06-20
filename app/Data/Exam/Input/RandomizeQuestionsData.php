<?php

declare(strict_types=1);

namespace App\Data\Exam\Input;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Data;

class RandomizeQuestionsData extends Data
{
    public function __construct(
        #[IntegerType, Min(1)]
        public readonly int $count
    ){}
}