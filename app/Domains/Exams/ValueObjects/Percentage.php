<?php

declare(strict_types=1);

namespace App\Domains\Exams\ValueObjects;

final class Percentage
{
    private function __construct(public readonly float $value) {}

    public static function fromRatio(float $earned, float $possible): self
    {
        if ($possible <= 0.0) {
            return new self(0.0);
        }

        $ratio = (max(0.0, $earned) / $possible) * 100;

        return new self(max(0.0, min(100.0, $ratio)));
    }
}
