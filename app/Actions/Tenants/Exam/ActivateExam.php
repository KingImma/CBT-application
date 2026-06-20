<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

class ActivateExam
{
    public function execute(Exam $exam, string $userId): Exam
    {
        return DB::transaction(function () use ($exam, $userId) {
            
            // The activate() method in HasLifecycle handles the state transition,
            // window end calculation, expected attempts, and throws exceptions if invalid.
            $exam->activate($userId)->save();

            return $exam->fresh();
        });
    }
}