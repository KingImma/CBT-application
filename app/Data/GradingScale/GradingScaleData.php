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

    public function getId(): string
    {
        return $this->id;
    }

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
