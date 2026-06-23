<?php

declare(strict_types=1);

namespace App\Data\Exam\Output;

class StudentExamQuestionData extends ExamQuestionData
{
    public function __construct(
        string $id,
        string $exam_id,
        string $question_id,
        int $order,
        ?float $marks,
        mixed $question,
        public readonly ?string $text_answer = null,
    ) {
        if ($question && method_exists($question, 'relationLoaded') && $question->relationLoaded('options')) {
            $question->options->makeHidden('is_correct');
        }
        
        parent::__construct($id, $exam_id, $question_id, $order, $marks, $question);
        $this->hideAnswers();
    }
}
