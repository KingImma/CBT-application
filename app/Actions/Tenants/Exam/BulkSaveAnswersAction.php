<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Support\Facades\DB;

class BulkSaveAnswersAction
{
    public function execute(ExamAttempt $attempt, array $answers): void
    {
        DB::transaction(function () use ($attempt, $answers) {
            foreach ($answers as $answerData) {
                ExamAnswer::updateOrCreate(
                    [
                        'attempt_id' => $attempt->id,
                        'question_id' => $answerData['question_id'],
                    ],
                    [
                        'selected_option_ids' => $answerData['selected_option_ids'] ?? null,
                        'text_answer' => $answerData['text_answer'] ?? null,
                        'ordering_answer' => $answerData['ordering_answer'] ?? null,
                        'matching_answer' => $answerData['matching_answer'] ?? null,
                        'answered_at' => now(),
                        'time_spent_seconds' => $answerData['time_spent_seconds'] ?? null,
                    ]
                );
            }
        });
    }
}
