<?php

declare(strict_types=1);

namespace App\Domains\Exams\ValueObjects;

use InvalidArgumentException;

final class Marks
{
    private const float MIN_MARKS = 0.0;

    private function __construct(public readonly float $value) {}

    public static function of(float $value): self
    {
        if ($value < self::MIN_MARKS) {
            throw new InvalidArgumentException("Marks cannot be negative, got {$value}.");
        }

        return new self($value);
    }

    public static function zero(): self
    {
        return new self(self::MIN_MARKS);
    }

    public function add(self $other): self
    {
        return new self($this->value + $other->value);
    }
}
