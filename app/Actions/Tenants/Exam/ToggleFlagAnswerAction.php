<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\ExamAnswer;
use Illuminate\Support\Facades\DB;

class ToggleFlagAnswerAction
{
    public function execute(ExamAnswer $answer): bool
    {
        return DB::transaction(function () use ($answer) {
            $answer->is_flagged = ! $answer->is_flagged;
            $answer->save();
            
            return $answer->is_flagged;
        });
    }
}
