<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output;

use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;

final class FitbResultQuestionData extends ResultQuestionData
{
    public readonly ?string $text_answer;

    /** @var array<int, array{content: string, content_format: string, case_sensitive: bool}> */
    public readonly array $acceptable_answers;

    public function __construct(
        string $question_id,
        string $type,
        string $content,
        string $content_format = 'plain_text',
        ?string $image_url = null,
        float $marks_available = 0,
        float $marks_awarded = 0,
        bool $is_correct = false,
        ?string $text_answer = null,
        array $acceptable_answers = [],
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
        $this->text_answer = $text_answer;
        $this->acceptable_answers = $acceptable_answers;
    }

    public static function fromAnswer(
        ExamAnswer $answer,
        ExamQuestion $examQuestion,
        Question $question,
    ): static {
        $acceptableAnswers = $question->options
            ->filter(fn ($o) => (bool) $o->is_correct)
            ->map(fn ($o) => [
                'content' => $o->content,
                'content_format' => $o->content_format ?? 'plain_text',
                'case_sensitive' => (bool) ($o->match_pair
                    ? json_decode($o->match_pair, true)['case_sensitive'] ?? false
                    : false),
            ])
            ->values()
            ->toArray();

        return new self(
            question_id: $question->id,
            type: $question->type,
            content: $question->content,
            content_format: $question->content_format ?? 'plain_text',
            image_url: $question->image_url,
            marks_available: (float) ($examQuestion->getEffectiveMarks() ?? $question->default_marks),
            marks_awarded: (float) ($answer->marks_awarded ?? 0),
            is_correct: (bool) $answer->is_correct,
            text_answer: $answer->text_answer,
            acceptable_answers: $acceptableAnswers,
        );
    }
}
