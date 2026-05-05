<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Actions\Tenants\Exam\SubmitExamAction;
use Illuminate\Support\Facades\DB;

class EndExamSessionAction
{
    public function __construct(
        private SubmitExamAction $submitAction
    ) {}

    public function execute(Exam $exam): Exam
    {
        if ($exam->status !== ExamStatus::Active->value) {
            throw new \RuntimeException('Only active exams can have their session ended.');
        }

        return DB::transaction(function () use ($exam) {
            // Auto-submit all remaining in-progress attempts
            $inProgressAttempts = ExamAttempt::where('exam_id', $exam->id)
                ->where('status', ExamAttemptStatus::In_progress->value)
                ->get();

            foreach ($inProgressAttempts as $attempt) {
                try {
                    $this->submitAction->execute($attempt);
                } catch (\RuntimeException $e) {
                    // Log but continue with other attempts
                }
            }

            // Move exam to grading status
            $exam->update([
                'status' => ExamStatus::Grading->value,
            ]);

            return $exam->fresh();
        });
    }
}
