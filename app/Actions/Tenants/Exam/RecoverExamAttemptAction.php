<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamAnswer;
use App\Actions\Tenants\Exam\GetExamQuestionsAction;

class RecoverExamAttemptAction
{
    public function __construct(
        private GetExamQuestionsAction $getQuestionsAction
    ) {}
    
    public function execute(ExamAttempt $attempt): array
    {
        $questionsData = $this->getQuestionsAction->execute($attempt);
        
        $answers = ExamAnswer::where('attempt_id', $attempt->id)
            ->get()
            ->keyBy('question_id');
        
        return [
            'attempt' => $attempt,
            'questions' => $questionsData['questions'],
            'order' => $questionsData['order'],
            'answers' => $answers,
            'time_remaining_seconds' => $attempt->getTimeRemainingSeconds(),
        ];
    }
}
