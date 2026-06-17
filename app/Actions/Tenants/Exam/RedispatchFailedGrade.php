<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Jobs\GradeExamAttempt;
use App\Models\Tenant\ExamAttempt;
use App\Support\Exam\ExamAttemptGuard;
use Illuminate\Support\Facades\DB;

final class RedispatchFailedGrade
{
    public function execute(ExamAttempt $attempt): ExamAttempt
    {
        ExamAttemptGuard::assertCanTransitionTo($attempt, ExamAttemptStatus::Grading);

        return DB::transaction(function () use ($attempt) {
            $locked = ExamAttempt::where('id', $attempt->id)
                ->lockForUpdate()
                ->firstOrFail();

            ExamAttemptGuard::assertCanTransitionTo($locked, ExamAttemptStatus::Grading);

            $locked->status = ExamAttemptStatus::Grading->value;
            $locked->save();

            GradeExamAttempt::dispatch($locked->id, (string) tenant('id'));

            return $locked->fresh();
        });
    }
}
