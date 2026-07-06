<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Actions\Base\UpdateAction;
use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;
use App\Actions\Tenants\Exam\ExamGuards;

final class SubmitExamForReview
{
    public function __construct(private UpdateAction $action) {}

    public function execute(Exam $exam): Exam
    {
        return $this->action->execute(
            $exam,
            [],
            guard: ExamGuards::canSubmitForReview(),
            prepare: fn (Exam $e, array $d) => [
                'status' => ExamStatus::Submitted->value,
            ],
        );
    }
}
