<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Support\Facades\DB;

class SaveAnswerAction
{
    public function execute(ExamAttempt $attempt, string $questionId, array $data): ExamAnswer
    {
        return DB::transaction(function () use ($attempt, $questionId, $data) {
            $answer = ExamAnswer::updateOrCreate(
                [
                    'attempt_id' => $attempt->id,
                    'question_id' => $questionId,
                ],
                [
                    'selected_option_ids' => $data['selected_option_ids'] ?? null,
                    'text_answer' => $data['text_answer'] ?? null,
                    'ordering_answer' => $data['ordering_answer'] ?? null,
                    'matching_answer' => $data['matching_answer'] ?? null,
                    'answered_at' => now(),
                    'time_spent_seconds' => $data['time_spent_seconds'] ?? null,
                ]
            );

            return $answer;
        });
    }
}
