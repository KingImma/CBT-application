<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Input;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Exists;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Uuid;
use Spatie\LaravelData\Data;

class SyncExamQuestionItemData extends Data
{
    public function __construct(
        #[Uuid, Exists(model: 'questions', column: 'id')]
        public readonly string $question_id,

        #[IntegeType, Min(1)]
        public readonly int $order,

        #[Numeric, Min(0), RequiredIf('is_marks_locked', true)]
        public readonly ?string $marks = null,

        #[BooleanType]
        public readonly bool $is_marks_locked = false
    ) {}
}
