<?php

declare(strict_types=1);

namespace App\Http\Resources;

class StudentExamQuestionResource extends ExamQuestionResource
{
    public function __construct($resource)
    {
        parent::__construct($resource);
        $this->showAnswers(false);
    }
}
