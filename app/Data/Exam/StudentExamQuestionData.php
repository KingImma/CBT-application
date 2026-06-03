<?php

declare(strict_types=1);

namespace App\Data\Exam;

class StudentExamQuestionData extends ExamQuestionData
{
    public function __construct(
        string $id,
        string $exam_id,
        string $question_id,
        int $order,
        ?float $marks,
        $question,
    ) {
        parent::__construct(
            id: $id,
            exam_id: $exam_id,
            question_id: $question_id,
            order: $order,
            marks: $marks,
            question: $question,
        );
        $this->showAnswers(false);
    }
}
