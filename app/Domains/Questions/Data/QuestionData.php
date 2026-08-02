<?php

declare(strict_types=1);

namespace App\Domains\Questions\Data;

use App\Enums\QuestionType;
use App\Models\Tenant\Question;
use Spatie\LaravelData\Resource;

abstract class QuestionData extends Resource
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $content,
        public readonly string $content_format = 'plain_text',
        public readonly ?string $image_url = null,
        public readonly bool $is_active = true,
        public readonly ?string $subject_id = null,
        public readonly ?string $class_level_id = null,
        public readonly ?string $class_level_name = null,
        public readonly ?string $subject_name = null,
    ) {}

    public static function fromQuestion(Question $question): static
    {
        return match ($question->type) {
            QuestionType::Mcq->value => new McqQuestionData(
                id: $question->id,
                type: $question->type,
                content: $question->content,
                content_format: $question->content_format ?? 'plain_text',
                image_url: $question->image_url,
                is_active: (bool) $question->is_active,
                subject_id: $question->subject_id,
                class_level_id: $question->class_level_id,
                class_level_name: $question->classLevel?->name,
                subject_name: $question->subject?->name,
                options: $question->options
                    ->map(
                        fn ($o) => [
                            'id' => $o->id,
                            'label' => $o->label,
                            'content' => $o->content,
                            'content_format' => $o->content_format ?? 'plain_text',
                            'image_url' => $o->image_url,
                            'is_correct' => (bool) $o->is_correct,
                            'order' => (int) $o->order,
                            'match_pair' => $o->match_pair,
                            'case_sensitive' => $o->case_sensitive,
                        ],
                    )
                    ->values()
                    ->toArray(),
                // Evaluate the correct count inline
                allow_multiple_answers: $question->options
                    ->filter(fn ($o) => (bool) $o->is_correct)
                    ->count() > 1,
            ),
            QuestionType::TrueFalse->value => new TrueFalseQuestionData(
                id: $question->id,
                type: $question->type,
                content: $question->content,
                content_format: $question->content_format ?? 'plain_text',
                image_url: $question->image_url,
                is_active: (bool) $question->is_active,
                subject_id: $question->subject_id,
                class_level_id: $question->class_level_id,
                class_level_name: $question->classLevel?->name,
                subject_name: $question->subject?->name,
                options: $question->options
                    ->map(
                        fn ($o) => [
                            'id' => $o->id,
                            'label' => $o->label,
                            'content' => $o->content,
                            'content_format' => $o->content_format ?? 'plain_text',
                            'image_url' => $o->image_url,
                            'is_correct' => (bool) $o->is_correct,
                            'order' => (int) $o->order,
                            'match_pair' => $o->match_pair,
                            'case_sensitive' => $o->case_sensitive,
                        ],
                    )
                    ->values()
                    ->toArray(),
            ),
            QuestionType::FillInBlank->value => new FitbQuestionData(
                id: $question->id,
                type: $question->type,
                content: $question->content,
                content_format: $question->content_format ?? 'plain_text',
                image_url: $question->image_url,
                is_active: (bool) $question->is_active,
                subject_id: $question->subject_id,
                class_level_id: $question->class_level_id,
                class_level_name: $question->classLevel?->name,
                subject_name: $question->subject?->name,
                acceptable_answers: $question->options
                    ->filter(fn ($o) => (bool) $o->is_correct)
                    ->map(
                        fn ($o) => [
                            'content' => $o->content,
                            'content_format' => $o->content_format ?? 'plain_text',
                            'case_sensitive' => (bool) ($o->match_pair
                                ? json_decode($o->match_pair, true)[
                                        'case_sensitive'
                                    ] ?? false
                                : false),
                        ],
                    )
                    ->values()
                    ->toArray(),
            ),
            default => throw new \InvalidArgumentException(
                "Unknown question type: {$question->type}",
            ),
        };
    }
}
