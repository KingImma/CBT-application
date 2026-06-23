<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

final class FitbStudentQuestionData extends StudentQuestionData
{
    /**
     * No options array is exposed to the student during the exam.
     * Acceptable answers are omitted to prevent payload inspection cheating.
     */
    public function __construct(
        string $id,
        string $exam_id,
        string $question_id,
        int $order,
        ?float $marks,
        string $type,
        string $content,
        ?string $image_url,
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
    }
}
