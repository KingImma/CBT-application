<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions;

use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

final class ForceCompleteExam
{
    public function __construct() {}

    public function execute(Exam $exam): Exam
    {
        return DB::transaction(function () use ($exam) {
            ExamLifecycleRules::canComplete()($exam);

            $exam->update([
                'status' => ExamStatus::Completed->value,
                'window_end' => now(),
            ]);

            return $exam->fresh();
        });
    }
}
