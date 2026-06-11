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

        return DB::transaction(function () use ($attempt, $questionId, $payload) {
            $attempt->refresh();

            if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
                throw new \RuntimeException('Attempt is no longer active.');
            }

            if ($attempt->getTimeRemainingSeconds() <= 0) {
                app(ExamSessionAction::class)->finalizeExpiredAttempt($attempt);
                throw new \RuntimeException('Exam time has expired.');
            }

            return ExamAnswer::updateOrCreate(
                [
                    'attempt_id' => $attempt->id,
                    'question_id' => $questionId,
                ],
                [
                    'selected_option_ids' => $payload['selected_option_ids'] ?? null,
                    'text_answer' => $payload['text_answer'] ?? null,
                    'answered_at' => now(),
                    'time_spent_seconds' => abs((int) now()->diffInSeconds($attempt->started_at, true)),
                ]
            );
        });
    }

    public function bulkSave(ExamAttempt $attempt, array $answers): array
    {
        return DB::transaction(function () use ($attempt, $answers) {
            $saved = [];

            foreach ($answers as $answerData) {
                $saved[] = $this->save($attempt, $answerData['question_id'], $answerData);
            }

            return $saved;
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
