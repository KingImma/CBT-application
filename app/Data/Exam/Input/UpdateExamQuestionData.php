<?php

declare(strict_types=1);

namespace App\Data\Exam\Input;

use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UpdateExamQuestionData extends Data
{
    public function __construct(
        #[Numeric, Min(0)]
        public readonly Optional|float|null $marks,
        
        #[IntegerType, Min(1)]
        public readonly Optional|int $order,
    ) {}
}
