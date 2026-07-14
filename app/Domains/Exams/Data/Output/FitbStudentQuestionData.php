<?php

declare(strict_types=1);

namespace App\Domains\Exams\Data\Output;

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
        string $content_format = 'plain_text',
        ?string $image_url = null,
    ) {
        parent::__construct(
            id: $id,
            exam_id: $exam_id,
            question_id: $question_id,
            order: $order,
            marks: $marks,
            type: $type,
            content: $content,
            content_format: $content_format,
            image_url: $image_url,
        );
    }
}
