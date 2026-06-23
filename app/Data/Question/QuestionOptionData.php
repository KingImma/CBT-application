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
        public readonly ?bool $case_sensitive,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function isCorrect(): bool
    {
        return $this->is_correct;
    }

    public function getOrder(): ?int
    {
        return $this->order;
    }

    public function getMatchPair(): ?string
    {
        return $this->match_pair;
    }

    public function getCaseSensitive(): ?bool
    {
        return $this->case_sensitive;
    }
}
