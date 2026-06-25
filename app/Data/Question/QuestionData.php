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
        public readonly ?string $class_level_name,
        public readonly ?string $subject_name,
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
                class_level_name: $question->classLevel?->name,
                subject_name: $question->subject?->name,
                options: $question->options->map(fn ($o) => [
                    'id' => $o->id,
                    'label' => $o->label,
                    'content' => $o->content,
                    'image_url' => $o->image_url,
                    'is_correct' => (bool) $o->is_correct,
                    'order' => (int) $o->order,
                    'match_pair' => $o->match_pair,
                    'case_sensitive' => $o->case_sensitive,
                ])->values()->toArray(),
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
                class_level_name: $question->classLevel?->name,
                subject_name: $question->subject?->name,
                options: $question->options->map(fn ($o) => [
                    'id' => $o->id,
                    'label' => $o->label,
                    'content' => $o->content,
                    'image_url' => $o->image_url,
                    'is_correct' => (bool) $o->is_correct,
                    'order' => (int) $o->order,
                    'match_pair' => $o->match_pair,
                    'case_sensitive' => $o->case_sensitive,
                ])->values()->toArray(),
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
                class_level_name: $question->classLevel?->name,
                subject_name: $question->subject?->name,
                acceptable_answers: $question->options
                    ->filter(fn ($o) => (bool) $o->is_correct)
                    ->map(fn ($o) => [
                        'content' => $o->content,
                        'case_sensitive' => (bool) ($o->match_pair ? json_decode($o->match_pair, true)['case_sensitive'] ?? false : false),
                    ])->values()->toArray(),
            ),
            default => throw new \InvalidArgumentException("Unknown question type: {$question->type}"),
        };
    }
}
