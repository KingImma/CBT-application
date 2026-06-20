<?php

declare(strict_types=1);

namespace App\Data\Question;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Resource;

class QuestionData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $content,
        public readonly ?float $default_marks,
        public readonly ?string $image_url,
        public readonly bool $is_active,
        #[WhenLoaded('options')]
        public readonly mixed $options,
        #[WhenLoaded('subject')]
        public readonly mixed $subject,
        #[WhenLoaded('classLevel')]
        public readonly mixed $classLevel,
        #[WhenLoaded('creator')]
        public readonly mixed $creator,
    ) {}
}
