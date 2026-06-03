<?php

declare(strict_types=1);

namespace App\Data\Question;

use Spatie\LaravelData\Resource;

class QuestionOptionData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $label,
        public readonly string $content,
        public readonly ?string $image_url,
        public readonly bool $is_correct,
        public readonly ?int $order,
        public readonly ?string $match_pair,
    ) {}
}
