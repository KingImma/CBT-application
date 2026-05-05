<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamAttempt;
use App\Models\Tenant\User;
use App\Enums\ExamAttemptStatus;
use Illuminate\Support\Facades\DB;

class CreateExamAttemptAction
{
    public function execute(Exam $exam, User $student): ExamAttempt
    {
        $lastAttemptNumber = $exam->attempts()->forStudent($student->id)->max('attempt_number') ?? 0;

        return DB::transaction(function () use ($exam, $student, $lastAttemptNumber) {
            $attempt = ExamAttempt::create([
                'exam_id' => $exam->id,
                'student_id' => $student->id,
                'attempt_number' => $lastAttemptNumber + 1,
                'status' => ExamAttemptStatus::In_progress->value,
                'started_at' => now(),
            ]);

            return $attempt;
        });
    }
}
