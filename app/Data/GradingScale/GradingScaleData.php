<?php

declare(strict_types=1);

namespace App\Data\GradingScale;

use Spatie\LaravelData\Resource;

class GradingScaleData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $grades,
        public readonly bool $is_default,
    ) {}
}
