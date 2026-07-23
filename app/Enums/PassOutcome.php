<?php

declare(strict_types=1);

namespace App\Enums;

use App\Domains\Exams\ValueObjects\Percentage;

enum PassOutcome: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case NotApplicable = 'not_applicable';

    public static function resolve(Percentage $scored, ?float $passMark): self
    {
        if ($passMark === null) {
            return self::NotApplicable;
        }

        return $scored->value >= $passMark ? self::Passed : self::Failed;
    }

    public function toNullableBool(): ?bool
    {
        return match ($this) {
            self::Passed => true,
            self::Failed => false,
            self::NotApplicable => null,
        };
    }
}
