<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

class DeleteExamAction
{
    public function execute(Exam $exam): void
    {
        if ($exam->status !== 'draft') {
            throw new \RuntimeException('Only draft exams can be deleted.');
        }

        DB::transaction(function () use ($exam) {
            $exam->delete();
        });
    }
}
