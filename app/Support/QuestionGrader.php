<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\QuestionType;
use Illuminate\Support\Collection;

class QuestionGrader
{
    public function isCorrect(
        string $questionType,
        Collection $options,
        array $selectedIds,
        ?string $textAnswer = null,
    ): bool {
        $type = QuestionType::tryFrom($questionType);

        if ($type === null) {
            return false;
        }

        return match ($type) {
            QuestionType::Mcq, QuestionType::TrueFalse => $this->gradeExactChoice($options, $selectedIds),
            QuestionType::FillInBlank => $this->gradeFillInTheBlank($options, $textAnswer),
        };
    }

    private function gradeExactChoice(Collection $options, array $selectedIds): bool
    {
        $correctIds = $options
            ->filter(fn (mixed $option): bool => (bool) data_get($option, 'is_correct'))
            ->map(fn (mixed $option): string => (string) data_get($option, 'id'))
            ->sort()
            ->values()
            ->all();

        $normalizedSelectedIds = collect($selectedIds)
            ->map(fn (mixed $selectedId): string => (string) $selectedId)
            ->sort()
            ->values()
            ->all();

        if ($correctIds === [] || $normalizedSelectedIds === []) {
            return false;
        }

        return $correctIds === $normalizedSelectedIds;
    }

    private function gradeFillInTheBlank(Collection $options, ?string $textAnswer): bool
    {
        if ($textAnswer === null) {
            return false;
        }

        $studentAnswer = trim($textAnswer);

        if ($studentAnswer === '') {
            return false;
        }

        return $options
            ->filter(fn (mixed $option): bool => (bool) data_get($option, 'is_correct'))
            ->contains(fn (mixed $option): bool => $this->matchesTextOption($option, $studentAnswer));
    }

    private function matchesTextOption(mixed $option, string $studentAnswer): bool
    {
        $acceptedAnswer = data_get($option, 'content');

        if (! is_string($acceptedAnswer)) {
            return false;
        }

        $acceptedAnswer = trim($acceptedAnswer);
        $caseSensitive = (bool) data_get($option, 'case_sensitive', false);

        if ($caseSensitive) {
            return hash_equals($acceptedAnswer, $studentAnswer);
        }

        return mb_strtolower($acceptedAnswer) === mb_strtolower($studentAnswer);
    }
}
