<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

class ExamResultPublishAction
{
    public function publishResults(Exam $exam): Exam
    {
        return DB::transaction(function () use ($exam) {
            $exam->publish();
            $exam->save();

            return $exam->fresh();
        });
    }

    public function unpublishResults(Exam $exam): Exam
    {
        return DB::transaction(function () use ($exam) {
            $exam->unpublish();
            $exam->save();

            return $exam->fresh();
        });
    }
}
