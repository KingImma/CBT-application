<?php

declare(strict_types=1);

namespace App\Data\Question;

use App\Enums\QuestionType;
use App\Models\Tenant\Question;
use Spatie\LaravelData\Resource;

abstract class QuestionData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $content,
        public readonly ?string $image_url,
        public readonly float $default_marks,
        public readonly bool $is_active,
        public readonly ?string $subject_id,
        public readonly ?string $class_level_id,
    ) {}

    public static function fromQuestion(Question $question): static
    {
        return match ($question->type) {
            QuestionType::Mcq->value => new McqQuestionData(
                id: $question->id,
                type: $question->type,
                content: $question->content,
                image_url: $question->image_url,
                default_marks: (float) $question->default_marks,
                is_active: (bool) $question->is_active,
                subject_id: $question->subject_id,
                class_level_id: $question->class_level_id,
                options: $question->options->map(fn ($o) => QuestionOptionData::from($o))->toArray(),
            ),
            QuestionType::TrueFalse->value => new TrueFalseQuestionData(
                id: $question->id,
                type: $question->type,
                content: $question->content,
                image_url: $question->image_url,
                default_marks: (float) $question->default_marks,
                is_active: (bool) $question->is_active,
                subject_id: $question->subject_id,
                class_level_id: $question->class_level_id,
                options: $question->options->map(fn ($o) => QuestionOptionData::from($o))->toArray(),
            ),
            QuestionType::FillInBlank->value => new FitbQuestionData(
                id: $question->id,
                type: $question->type,
                content: $question->content,
                image_url: $question->image_url,
                default_marks: (float) $question->default_marks,
                is_active: (bool) $question->is_active,
                subject_id: $question->subject_id,
                class_level_id: $question->class_level_id,
                acceptable_answers: $question->options
                    ->filter(fn ($o) => (bool) $o->is_correct)
                    ->map(fn ($o) => new FitbAcceptableAnswerData(
                        content: $o->content,
                        case_sensitive: (bool) ($o->match_pair ? json_decode($o->match_pair, true)['case_sensitive'] ?? false : false),
                    ))->values()->toArray(),
            ),
            default => throw new \InvalidArgumentException("Unknown question type: {$question->type}"),
        };
    }
}
