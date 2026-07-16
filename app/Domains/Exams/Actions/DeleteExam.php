<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions;

use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;
use App\Domains\Exams\Support\ExamLifecycleRules;

final class DeleteExam
{
    public function __construct() {}

    public function execute(Exam $exam): void
    {
        DB::transaction(function () use ($exam) {
            ExamLifecycleRules::canDelete()($exam);

            $exam->attempts()->each(fn ($attempt) => $attempt->answers()->delete());
            $exam->attempts()->delete();
            $exam->examQuestions()->delete();
            $exam->delete();
        });
    }
}
