<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions;

use App\Domains\Exams\Events\ExamActivated;
use App\Domains\Exams\Support\ExamLifecycleRules;
use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

final class ActivateExam
{
    public function __construct() {}

    public function execute(Exam $exam, string $userId): Exam
    {
        return DB::transaction(function () use ($exam, $userId) {
            ExamLifecycleRules::canActivate()($exam);

            $exam->update([
                'status' => ExamStatus::Active->value,
                'window_end' => $exam->scheduled_start->copy()->addMinutes($exam->duration_minutes * 2),
                'expected_attempts' => $exam->expectedAttempts(),
            ]);

            $exam->forceFill([
                'approved_by' => $userId,
                'approved_at' => now(),
            ])->save();

            $fresh = $exam->fresh();

            event(new ExamActivated($fresh));

            return $fresh;
        });
    }
}
