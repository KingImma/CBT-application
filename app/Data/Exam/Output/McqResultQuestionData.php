<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;

final class McqResultQuestionData extends ResultQuestionData
{
    /** @var array<int, array{id: string, label: ?string, content: string, image_url: ?string, is_correct: bool}> */
    public readonly array $options;

    /** @var array<int, array{id: string, label: ?string, content: string, image_url: ?string, is_correct: bool}> */
    public readonly array $selected_options;

    public readonly bool $allow_multiple_answers;

    public function __construct(
        string $question_id,
        string $type,
        string $content,
        ?string $image_url,
        float $marks_available,
        float $marks_awarded,
        bool $is_correct,
        array $options,
        array $selected_options,
        bool $allow_multiple_answers,
    ) {
        parent::__construct(
            question_id: $question_id,
            type: $type,
            content: $content,
            image_url: $image_url,
            marks_available: $marks_available,
            marks_awarded: $marks_awarded,
            is_correct: $is_correct,
        );
        $this->options = $options;
        $this->selected_options = $selected_options;
        $this->allow_multiple_answers = $allow_multiple_answers;
    }

    public static function fromAnswer(
        ExamAnswer $answer,
        ExamQuestion $examQuestion,
        Question $question,
    ): static {
        $optionsMap = $question->options->keyBy('id');

        $selectedOptions = collect($answer->selected_option_ids ?? [])
            ->map(fn (string $optionId) => $optionsMap->has($optionId) ? [
                'id' => $optionsMap[$optionId]->id,
                'label' => $optionsMap[$optionId]->label,
                'content' => $optionsMap[$optionId]->content,
                'image_url' => $optionsMap[$optionId]->image_url,
                'is_correct' => (bool) $optionsMap[$optionId]->is_correct,
            ] : null)
            ->filter()
            ->values()
            ->toArray();

        $allOptions = $question->options->map(fn ($opt) => [
            'id' => $opt->id,
            'label' => $opt->label,
            'content' => $opt->content,
            'image_url' => $opt->image_url,
            'is_correct' => (bool) $opt->is_correct,
        ])->toArray();

        $correctCount = $question->options->filter(fn($opt) => $opt->is_correct)->count();

        return new self(
            question_id: $question->id,
            type: $question->type,
            content: $question->content,
            image_url: $question->image_url,
            marks_available: (float) ($examQuestion->getEffectiveMarks() ?? $question->default_marks),
            marks_awarded: (float) ($answer->marks_awarded ?? 0),
            is_correct: (bool) $answer->is_correct,
            options: $allOptions,
            selected_options: $selectedOptions,
            allow_multiple_answers: collect($examQuestion->question->options)->filter(fn($o) => (bool) $o['is_correct'])->count() > 1,
        );
    }
}
