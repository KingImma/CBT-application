<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

class GradeTheoryAnswerAction
{
    public function execute(ExamAnswer $answer, float $marks, string $feedback, User $gradedBy): ExamAnswer
    {
        return DB::transaction(function () use ($answer, $marks, $feedback, $gradedBy) {
            $answer->update([
                'marks_awarded' => $marks,
                'teacher_feedback' => $feedback,
                'graded_by' => $gradedBy->id,
            ]);

            return $answer->fresh();
        });
    }
}
