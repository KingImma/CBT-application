<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Tenants\Exam\ValidateExamStartAction;
use App\Actions\Tenants\Exam\CreateExamAttemptAction;
use App\Actions\Tenants\Exam\GetExamQuestionsAction;
use App\Models\Tenant\Exam;
use App\Models\Tenant\User;

class ExamTakingService
{
    public function __construct(
        private ValidateExamStartAction $validateAction,
        private CreateExamAttemptAction $createAttemptAction,
        private GetExamQuestionsAction $getQuestionsAction,
    ) {}

    public function start(Exam $exam, User $student): array
    {
        $this->validateAction->execute($exam, $student);
        
        $attempt = $this->createAttemptAction->execute($exam, $student);
        
        $questionsData = $this->getQuestionsAction->execute($attempt);
        
        return [
            'attempt' => $attempt,
            'questions' => $questionsData['questions'],
            'order' => $questionsData['order'],
        ];
    }
}
