<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Support\Facades\DB;

class ExamAnswerAction
{
    public function save(ExamAttempt $attempt, string $questionId, array $payload): ExamAnswer
    {
        if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
            throw new \RuntimeException('Attempt is no longer active.');
        }

        if ($attempt->getTimeRemainingSeconds() <= 0) {
            app(ExamSessionAction::class)->submit($attempt);
            throw new \RuntimeException('Exam time has expired.');
        }

        return DB::transaction(function () use ($attempt, $questionId, $payload) {
            return ExamAnswer::updateOrCreate(
                [
                    'attempt_id' => $attempt->id,
                    'question_id' => $questionId,
                ],
                [
                    'selected_option_ids' => $payload['selected_option_ids'] ?? null,
                    'text_answer' => $payload['text_answer'] ?? null,
                    'answered_at' => now(),
                    'time_spent_seconds' => $payload['time_spent_seconds'] ?? null,
                ]
            );
        });
    }

    public function bulkSave(ExamAttempt $attempt, array $answers): void
    {
        DB::transaction(function () use ($attempt, $answers) {
            foreach ($answers as $answerData) {
                $this->save($attempt, $answerData['question_id'], $answerData);
            }
        });
    }

    public function toggleFlag(ExamAnswer $answer): bool
    {
        return DB::transaction(function () use ($answer) {
            $answer->is_flagged = ! $answer->is_flagged;
            $answer->save();

            return $answer->is_flagged;
        });
    }
}
