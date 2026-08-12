<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data;

use Spatie\LaravelData\Data;

class MaterializeExamQuestionRequest extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly int $order,
        public readonly string $content,
        public readonly ?string $imageUrl,
        public readonly float $marks,
        /** @var MaterializeExamOptionRequest[] */
        public readonly array $options,
    ) {}
}