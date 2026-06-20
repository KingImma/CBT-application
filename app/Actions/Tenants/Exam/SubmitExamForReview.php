<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

class SubmitExamForReview
{
    public function execute(Exam $exam): Exam
    {
        return DB::transaction(function () use ($exam) {
            $exam->submitForReview()->save();
            return $exam->fresh();
        });
    }
}