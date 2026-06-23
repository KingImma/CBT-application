<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

use App\Enums\QuestionType;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamQuestion;
use App\Models\Tenant\Question;
use Spatie\LaravelData\Data;

abstract class ResultQuestionData extends Data
{
    public function __construct(
        public readonly string $question_id,
        public readonly string $type,
        public readonly string $content,
        public readonly ?string $image_url,
        public readonly float $marks_available,
        public readonly float $marks_awarded,
        public readonly bool $is_correct,
    ) {}

    public static function fromAnswer(
        ExamAnswer $answer,
        ExamQuestion $examQuestion,
        Question $question,
    ): static {
        return match ($question->type) {
            QuestionType::Mcq->value => McqResultQuestionData::fromAnswer($answer, $examQuestion, $question),
            QuestionType::TrueFalse->value => TrueFalseResultQuestionData::fromAnswer($answer, $examQuestion, $question),
            QuestionType::FillInBlank->value => FitbResultQuestionData::fromAnswer($answer, $examQuestion, $question),
            default => throw new \InvalidArgumentException("Unknown question type: {$question->type}"),
        };
    }
}
