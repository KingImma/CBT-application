<?php

namespace App\Enums;

enum QuestionType: string
{
    case Mcq = 'mcq';
    case TrueFalse = 'true_false';
    case FillInBlank = 'fill_in_blank';

    public function minCorrectOptions(): int
    {
        return 1;
    }

    public function maxCorrectOptions(): ?int
    {
        return match ($this) {
            self::TrueFalse => 1,
            self::Mcq, self::FillInBlank => null,
        };
    }

    public function minOptions(): int
    {
        return match ($this) {
            self::FillInBlank => 1,
            self::Mcq, self::TrueFalse => 2,
        };
    }

    public function requiredOptionCount(): ?int
    {
        return match ($this) {
            self::TrueFalse => 2,
            self::Mcq, self::FillInBlank => null,
        };
    }

    public function isTextBased(): bool
    {
        return $this === self::FillInBlank;
    }
}
