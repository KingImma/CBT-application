<?php

declare(strict_types=1);

namespace App\Data\Values;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class ExamAttemptSettings implements Castable
{
    public function __construct(
        private readonly array $questionOrder = [],
    ) {}

    public function getQuestionOrder(): array
    {
        return $this->questionOrder;
    }

    public static function fromArray(?array $data): self
    {
        if ($data === null) {
            return new self;
        }

        return new self(
            questionOrder: (array) ($data['question_order'] ?? []),
        );
    }

    public function toArray(): array
    {
        return [
            'question_order' => $this->getQuestionOrder(),
        ];
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes): ExamAttemptSettings
            {
                return ExamAttemptSettings::fromArray(json_decode($value, true));
            }

            public function set($model, string $key, $value, array $attributes): string
            {
                if ($value instanceof ExamAttemptSettings) {
                    return json_encode($value->toArray());
                }

                if (is_array($value)) {
                    return json_encode($value);
                }

                return $value;
            }
        };
    }
}
