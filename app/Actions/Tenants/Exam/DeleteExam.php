<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Actions\Base\DeleteAction;
use App\Models\Tenant\Exam;

final class DeleteExam
{
    public function __construct(private DeleteAction $action) {}

    public function execute(Exam $exam): void
    {
        $this->action->execute(
            $exam,
            guard: ExamGuards::canDelete(),
            cascade: function (Exam $e) {
                $e->attempts()->each(fn ($attempt) => $attempt->answers()->delete());
                $e->attempts()->delete();
                $e->examQuestions()->delete();
            },
        );
    }
}
