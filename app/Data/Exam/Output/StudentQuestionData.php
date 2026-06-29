<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

use App\Enums\QuestionType;
use App\Models\Tenant\ExamQuestion;
use Spatie\LaravelData\Data;

abstract class StudentQuestionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $exam_id,
        public readonly string $question_id,
        public readonly string $type,
        public readonly string $content,
        public readonly ?string $image_url,
        public readonly int $order,
        public readonly ?float $marks,
    ) {}

    public static function fromExamQuestion(ExamQuestion $examQuestion): static
    {
        $question = $examQuestion->question;
        $marks = $examQuestion->marks !== null ? (float) $examQuestion->marks : null;
        $order = (int) $examQuestion->order;

        return match ($question->type) {
            QuestionType::Mcq->value => new McqStudentQuestionData(
                id: $examQuestion->id,
                exam_id: $examQuestion->exam_id,
                question_id: $examQuestion->question_id,
                order: $order,
                marks: $marks,
                type: $question->type,
                content: $question->content,
                image_url: $question->image_url,
                options: $question->options->map(fn ($o) => [
                    'id' => $o->id,
                    'content' => $o->content,
                ])->toArray(),
                allow_multiple_answers: $question->options->where('is_correct', true)->count() > 1
            ),
            QuestionType::TrueFalse->value => new TrueFalseStudentQuestionData(
                id: $examQuestion->id,
                exam_id: $examQuestion->exam_id,
                question_id: $examQuestion->question_id,
                order: $order,
                marks: $marks,
                type: $question->type,
                content: $question->content,
                image_url: $question->image_url,
                options: $question->options->map(fn ($o) => [
                    'id' => $o->id,
                    'content' => $o->content,
                ])->toArray(),
            ),
            QuestionType::FillInBlank->value => new FitbStudentQuestionData(
                id: $examQuestion->id,
                exam_id: $examQuestion->exam_id,
                question_id: $examQuestion->question_id,
                order: $order,
                marks: $marks,
                type: $question->type,
                content: $question->content,
                image_url: $question->image_url,
            ),
            default => throw new \InvalidArgumentException("Unknown question type: {$question->type}"),
        };
    }

    /**
     * @param  iterable<int, ExamQuestion>  $examQuestions
     * @return list<StudentQuestionData>
     */
    public static function collectFromExamQuestions(iterable $examQuestions): array
    {
        $result = [];
        foreach ($examQuestions as $eq) {
            $result[] = self::fromExamQuestion($eq);
        }

        return $result;
    }
}
