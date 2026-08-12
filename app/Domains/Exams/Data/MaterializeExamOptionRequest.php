<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data;

use Spatie\LaravelData\Data;

class MaterializeExamOptionRequest extends Data
{
    public function __construct(
        public readonly ?string $label,
        public readonly string $content,
        public readonly ?string $imageUrl,
        public readonly bool $isCorrect,
        public readonly int $order,
    ) {}
}