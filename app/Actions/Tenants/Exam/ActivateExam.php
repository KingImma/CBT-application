<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Actions\Base\UpdateAction;
use App\Enums\ExamStatus;
use App\Events\ExamActivated;
use App\Models\Tenant\Exam;

final class ActivateExam
{
    public function __construct(private UpdateAction $action) {}

    public function execute(Exam $exam, string $userId): Exam
    {
        return $this->action->execute(
            $exam,
            ['user_id' => $userId],
            guard: ExamGuards::canActivate(),
            prepare: fn (Exam $e, array $d) => [
                'status'            => ExamStatus::Active->value,
                'approved_by'       => $d['user_id'],
                'approved_at'       => now(),
                'window_end'        => $e->scheduled_start->copy()->addMinutes($e->duration_minutes * 2),
                'expected_attempts' => $e->expectedAttempts(),
            ],
            after: fn (Exam $e, array $d) => event(new ExamActivated($e)),
        );
    }
}
