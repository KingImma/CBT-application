<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use DomainException;
use Illuminate\Support\Facades\DB;

class DeleteExam
{
    public function execute(Exam $exam): void
    {
        $this->ensureExamIsDeletable($exam);

        DB::transaction(fn () => $this->performDeletion($exam));
    }

    private function ensureExamIsDeletable(Exam $exam): void
    {
        throw_if(
            $exam->isActive(),
            DomainException::class,
            'Cannot delete an active exam.'
        );

        throw_if(
            $exam->isPublished(),
            DomainException::class,
            'Cannot delete a pubished exam.'
        );

        throw_if(
            $exam->isCompleted(),
            DomainException::class,
            "Cannot delete an exam with {$exam->completed_attempts} completed attempt(s). Results would be permanently lost."
        );
    }

    private function performDeletion(Exam $exam): void
    {
        $exam->attempts()->delete();

        $exam->examQuestions()->delete();

        $exam->delete();
    }
}
