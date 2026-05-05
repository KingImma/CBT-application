<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

class UpdateExamAction
{
    public function execute(Exam $exam, array $data): Exam
    {
        if ($exam->status !== 'draft') {
            throw new \RuntimeException('Only draft exams can be updated.');
        }

        return DB::transaction(function () use ($exam, $data) {
            $exam->update($data);
            return $exam->fresh();
        });
    }
}
