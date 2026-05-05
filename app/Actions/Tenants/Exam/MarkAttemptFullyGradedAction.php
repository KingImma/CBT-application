<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\ExamAnswer;
use Illuminate\Support\Facades\DB;

class MarkAttemptFullyGradedAction
{
    public function execute(ExamAttempt $attempt): ExamAttempt
    {
        $allGraded = $attempt->answers()
            ->whereNull('marks_awarded')
            ->whereHas('question', fn ($q) => $q->whereIn('type', ['essay', 'short_answer']))
            ->doesntExist();

        if (! $allGraded) {
            throw new \RuntimeException('Not all theory answers have been graded.');
        }

        return DB::transaction(function () use ($attempt) {
            $attempt->update([
                'is_theory_graded' => true,
                'status' => ExamAttemptStatus::Graded->value,
            ]);

            return $attempt->fresh();
        });
    }
}
