<?php

namespace App\Enums;

enum SubmissionQuestionType: string
{
    case Mcq = 'mcq';
    case TrueFalse = 'true_false';
    case FillInBlank = 'fill_in_blank';

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

    /**
     * Map an authored submission question type onto the shared question bank's
     * QuestionType so approved submissions can materialise real exam questions.
     */
    public function toQuestionType(): QuestionType
    {
        return QuestionType::from($this->value);
    }
}
