<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Tenants\Exam\AutoGradeAnswerAction;
use App\Actions\Tenants\Exam\RecomputeAttemptScoreAction;
use App\Actions\Tenants\Exam\MarkAttemptFullyGradedAction;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\ExamAttempt;

class ExamGradingService
{
    public function __construct(
        private AutoGradeAnswerAction $autoGradeAction,
        private RecomputeAttemptScoreAction $recomputeAction,
        private MarkAttemptFullyGradedAction $markGradedAction,
    ) {}

    public function gradeTheoryAnswer(ExamAttempt $attempt, string $answerId, float $marks, string $feedback, $gradedBy): void
    {
        // This is handled by GradeTheoryAnswerAction directly
    }

    public function recomputeScore(ExamAttempt $attempt): ExamAttempt
    {
        return $this->recomputeAction->execute($attempt);
    }

    public function markFullyGraded(ExamAttempt $attempt): ExamAttempt
    {
        return $this->markGradedAction->execute($attempt);
    }

    public function checkAndCompleteExam(ExamAttempt $attempt): void
    {
        $this->recomputeAction->execute($attempt);
    }
}
