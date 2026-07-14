<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions;

use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

final class SubmitExamForReview
{
    public function __construct() {}

    public function execute(Exam $exam): Exam
    {
        return DB::transaction(function () use ($exam) {
            ExamLifecycleRules::canSubmitForReview()($exam);

            $exam->update(['status' => ExamStatus::Submitted->value]);

            return $exam->fresh();
        });
    }
}
