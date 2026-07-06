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
        public readonly string $content_format = 'plain_text',
        public readonly ?string $image_url = null,
        public readonly bool $is_correct = false,
        public readonly ?int $order = null,
        public readonly ?string $match_pair = null,
        public readonly ?bool $case_sensitive = null,
    ) {}
}
