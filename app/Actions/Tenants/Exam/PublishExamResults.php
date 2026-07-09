<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Actions\Base\UpdateAction;
use App\Enums\ExamStatus;
use App\Models\Tenant\Exam;

final class PublishExamResults
{
    public function __construct(private UpdateAction $action) {}

    public function execute(Exam $exam): Exam
    {
        return $this->action->execute(
            $exam,
            [],
            guard: ExamGuards::isCompleted(),
            prepare: fn (Exam $e, array $d) => [
                'status' => ExamStatus::Published->value,
            ],
            force: fn (Exam $e, array $d) => [
                'published_at' => now(),
            ],
            // after: fn (Exam $e, array $d) => event(new ResultReleased($e)),
        );
    }
}
