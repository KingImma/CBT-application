<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;

final class McqResultQuestionData extends ResultQuestionData
{
    /** @var array<int, array{id: string, label: ?string, content: string, content_format: string, image_url: ?string, is_correct: bool}> */
    public readonly array $options;

    /** @var array<int, array{id: string, label: ?string, content: string, content_format: string, image_url: ?string, is_correct: bool}> */
    public readonly array $selected_options;

    public readonly bool $allow_multiple_answers;

    public function __construct(
        string $question_id,
        string $type,
        string $content,
        string $content_format = 'plain_text',
        ?string $image_url = null,
        float $marks_available = 0,
        float $marks_awarded = 0,
        bool $is_correct = false,
        array $options = [],
        array $selected_options = [],
        bool $allow_multiple_answers = false,
    ) {
        parent::__construct(
            question_id: $question_id,
            type: $type,
            content: $content,
            content_format: $content_format,
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
            ->map(
                fn (string $optionId) => $optionsMap->has($optionId)
                    ? [
                        'id' => $optionsMap[$optionId]->id,
                        'label' => $optionsMap[$optionId]->label,
                        'content' => $optionsMap[$optionId]->content,
                        'content_format' => $optionsMap[$optionId]->content_format ?? 'plain_text',
                        'image_url' => $optionsMap[$optionId]->image_url,
                        'is_correct' => (bool) $optionsMap[$optionId]->is_correct,
                    ]
                    : null,
            )
            ->filter()
            ->values()
            ->toArray();

        $allOptions = $question->options
            ->map(
                fn ($opt) => [
                    'id' => $opt->id,
                    'label' => $opt->label,
                    'content' => $opt->content,
                    'content_format' => $opt->content_format ?? 'plain_text',
                    'image_url' => $opt->image_url,
                    'is_correct' => (bool) $opt->is_correct,
                ],
            )
            ->toArray();

        $correctCount = $question->options
            ->filter(fn ($opt) => $opt->is_correct)
            ->count();

        return new self(
            question_id: $question->id,
            type: $question->type,
            content: $question->content,
            content_format: $question->content_format ?? 'plain_text',
            image_url: $question->image_url,
            marks_available: (float) ($examQuestion->getEffectiveMarks() ??
                $question->default_marks),
            marks_awarded: (float) ($answer->marks_awarded ?? 0),
            is_correct: (bool) $answer->is_correct,
            options: $allOptions,
            selected_options: $selectedOptions,
            allow_multiple_answers: $correctCount > 1,
        );
    }
}
