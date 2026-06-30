<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

final class McqStudentQuestionData extends StudentQuestionData
{
    /**
     * Options visible to the student during the exam.
     * The `is_correct` flag is STRIPPED to prevent answer leakage.
     *
     * @var array<int, array{id: string, content: string}>
     */
    public readonly array $options;

    public readonly bool $allow_multiple_answers;

    public function __construct(
        string $id,
        string $exam_id,
        string $question_id,
        int $order,
        ?float $marks,
        string $type,
        string $content,
        ?string $image_url,
        array $options,
        bool $allow_multiple_answers,
    ) {
        parent::__construct(
            id: $id,
            exam_id: $exam_id,
            question_id: $question_id,
            order: $order,
            marks: $marks,
            type: $type,
            content: $content,
            image_url: $image_url,
        );
        $this->options = $options;
        $this->allow_multiple_answers = $allow_multiple_answers;
    }
}
