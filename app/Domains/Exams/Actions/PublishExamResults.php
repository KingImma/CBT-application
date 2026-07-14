<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions;

use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

final class PublishExamResults
{
    public function __construct() {}

    public function execute(Exam $exam): Exam
    {
        return DB::transaction(function () use ($exam) {
            ExamLifecycleRules::isCompleted()($exam);

            $exam->update(['status' => ExamStatus::Published->value]);
            $exam->forceFill(['published_at' => now()])->save();

            return $exam->fresh();
        });
    }
}
