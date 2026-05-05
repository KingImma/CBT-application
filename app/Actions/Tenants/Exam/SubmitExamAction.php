<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamAnswer;
use App\Actions\Tenants\Exam\AutoGradeAnswerAction;
use Illuminate\Support\Facades\DB;

class SubmitExamAction
{
    public function __construct(
        private AutoGradeAnswerAction $autoGradeAction
    ) {}
    
    public function execute(ExamAttempt $attempt): ExamAttempt
    {
        if ($attempt->status !== ExamAttemptStatus::In_progress->value) {
            throw new \RuntimeException('Only in-progress attempts can be submitted.');
        }
        
        return DB::transaction(function () use ($attempt) {
            // Auto-grade objective questions
            $answers = ExamAnswer::where('attempt_id', $attempt->id)->get();
            
            foreach ($answers as $answer) {
                $this->autoGradeAction->execute($answer);
            }
            
            $hasTheory = $attempt->answers()
                ->whereHas('question', fn ($q) => $q->whereIn('type', ['essay', 'short_answer']))
                ->exists();
            
            $attempt->update([
                'status' => $hasTheory ? ExamAttemptStatus::Grading->value : ExamAttemptStatus::Graded->value,
                'submitted_at' => now(),
                'time_spent_seconds' => now()->diffInSeconds($attempt->started_at),
            ]);
            
            // Recompute score
            app(RecomputeAttemptScoreAction::class)->execute($attempt);
            
            return $attempt->fresh();
        });
    }
}
