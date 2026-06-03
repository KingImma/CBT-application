<?php

declare(strict_types=1);

namespace App\Data\Question;

use Spatie\LaravelData\Attributes\WhenLoaded;
use Spatie\LaravelData\Optional;
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
        public readonly Optional|array $options,
        #[WhenLoaded('subject')]
        public readonly Optional|array $subject,
        #[WhenLoaded('classLevel')]
        public readonly Optional|array $classLevel,
        #[WhenLoaded('creator')]
        public readonly Optional|array $creator,
    ) {}
}
