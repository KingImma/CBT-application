<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Tenants\Exam\EndExamSessionAction;
use App\Actions\Tenants\Exam\SubmitExamAction;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;

class ExamSessionService
{
    public function __construct(
        private EndExamSessionAction $endSessionAction,
        private SubmitExamAction $submitAction,
    ) {}

    public function endSession(Exam $exam): Exam
    {
        return $this->endSessionAction->execute($exam);
    }

    public function autoSubmitExpiredAttempts(Exam $exam): void
    {
        $attempts = ExamAttempt::where('exam_id', $exam->id)
            ->where('status', 'in_progress')
            ->get();

        foreach ($attempts as $attempt) {
            try {
                $this->submitAction->execute($attempt);
            } catch (\RuntimeException $e) {
                // Log but continue
            }
        }
    }
}
