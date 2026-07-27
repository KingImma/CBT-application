<?php

declare(strict_types=1);

namespace App\Domains\Exams\ValueObjects;

final class Percentage
{
    private const float MIN_PERCENTAGE = 0.0;
    private const float MAX_PERCENTAGE = 100.0;

    private function __construct(public readonly float $value) {}

    public static function fromRatio(float $earned, float $possible): self
    {
        if ($possible <= self::MIN_PERCENTAGE) {
            return new self(self::MIN_PERCENTAGE);
        }

        $percentage = (max(self::MIN_PERCENTAGE, $earned) / $possible) * self::MAX_PERCENTAGE;

        return new self (self::clamp($percentage));
    }

    private static function clamp(float $percentage): float
    {
        return max(self::MIN_PERCENTAGE, min(self::MAX_PERCENTAGE, $percentage));
    }
}

