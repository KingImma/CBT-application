<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;

final class FitbResultQuestionData extends ResultQuestionData
{
    public readonly ?string $text_answer;

    /** @var array<int, array{content: string, case_sensitive: bool}> */
    public readonly array $acceptable_answers;

    public function __construct(
        string $question_id,
        string $type,
        string $content,
        ?string $image_url,
        float $marks_available,
        float $marks_awarded,
        bool $is_correct,
        ?string $text_answer,
        array $acceptable_answers,
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
        $this->text_answer = $text_answer;
        $this->acceptable_answers = $acceptable_answers;
    }

    public static function fromAnswer(
        ExamAnswer $answer,
        ExamQuestion $examQuestion,
        Question $question,
    ): self {
        $acceptableAnswers = $question->options
            ->filter(fn ($o) => (bool) $o->is_correct)
            ->map(fn ($o) => [
                'content' => $o->content,
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
            image_url: $question->image_url,
            marks_available: (float) ($examQuestion->getEffectiveMarks() ?? $question->default_marks),
            marks_awarded: (float) ($answer->marks_awarded ?? 0),
            is_correct: (bool) $answer->is_correct,
            text_answer: $answer->text_answer,
            acceptable_answers: $acceptableAnswers,
        );
    }
}
