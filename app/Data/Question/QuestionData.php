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

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getDefaultMarks(): ?float
    {
        return $this->default_marks;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function getOptions(): mixed
    {
        return $this->options;
    }

    public function getSubject(): mixed
    {
        return $this->subject;
    }

    public function getClassLevel(): mixed
    {
        return $this->classLevel;
    }

    public function getCreator(): mixed
    {
        return $this->creator;
    }
}
