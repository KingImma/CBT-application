<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Actions\Base\UpdateAction;
use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use App\Actions\Tenants\Exam\ExamGuards;

final class ForceCompleteExam
{
    public function __construct(private UpdateAction $action) {}

    public function execute(Exam $exam): Exam
    {
        return $this->action->execute(
            $exam,
            [],
            guard: ExamGuards::canComplete(),
            prepare: fn (Exam $e, array $d) => [
                'status' => ExamStatus::Completed->value,
                'window_end' => now(),
            ],
        );
    }
}
