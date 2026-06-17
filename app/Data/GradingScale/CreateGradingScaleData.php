<?php

declare(strict_types=1);

namespace App\Data\GradingScale;

use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class CreateGradingScaleData extends Data
{
    public function __construct(
        #[Required, StringType, Max(100)]
        public string $name,
        #[Required, ArrayType, Min(1)]
        public array $grades,
        #[BooleanType]
        public bool $is_default = false,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getGrades(): array
    {
        return $this->grades;
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }
}
