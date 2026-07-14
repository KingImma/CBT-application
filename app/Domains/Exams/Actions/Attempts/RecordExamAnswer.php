<?php

declare(strict_types=1);

namespace App\Domains\Exams\Actions\Attempts;

use App\Domains\Exams\Events\ExamSessionStateUpdated;
use App\Domains\Exams\Support\ExamAttemptLifecycleRules;
use App\Domains\Exams\Support\ExamSessionStateStore;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class RecordExamAnswer
{
    public function __construct(private ExamSessionStateStore $stateStore) {}

    public function save(ExamAttempt $attempt, string $questionId, array $payload): ExamAnswer
    {
        (ExamAttemptLifecycleRules::isInProgress())($attempt);

        if ($attempt->getTimeRemainingSeconds() <= 0) {
            throw new \RuntimeException('Exam time has expired');
        }

        $answer = $this->upsertWithRetry($attempt, $questionId, $payload);

        $this->touchSessionState($attempt);

        return $answer;
    }

    public function bulkSave(ExamAttempt $attempt, array $answers): array
    {
        (ExamAttemptLifecycleRules::isInProgress())($attempt);

        $saved = DB::transaction(function () use ($attempt, $answers) {
            if ($attempt->getTimeRemainingSeconds() <= 0) {
                throw new \RuntimeException('Exam time has expired.');
            }

            return array_map(
                fn ($a) => $this->upsertWithRetry($attempt, $a['question_id'], $a),
                $answers
            );
        });

        $this->touchSessionState($attempt);

        return $saved;
    }

    public function toggleFlag(ExamAnswer $answer): bool
    {
        return DB::transaction(function () use ($answer) {
            $answer->is_flagged = ! $answer->is_flagged;
            $answer->save();

            return $answer->is_flagged;
        });
    }

    private function upsertWithRetry(ExamAttempt $attempt, string $questionId, array $payload, int $tries = 3): ExamAnswer
    {
        $attemptCount = 0;

        while (true) {
            try {
                return DB::transaction(fn () => ExamAnswer::updateOrCreate(
                    ['attempt_id' => $attempt->id, 'question_id' => $questionId],
                    [
                        'selected_option_ids' => $payload['selected_option_ids'] ?? null,
                        'text_answer' => $payload['text_answer'] ?? null,
                        'answered_at' => now(),
                        'time_spent_seconds' => abs((int) now()->diffInSeconds($attempt->started_at, true)),
                    ]
                ));
            } catch (QueryException $e) {
                if ($e->getCode() !== '23505' || ++$attemptCount >= $tries) {
                    throw $e;
                }
                usleep(50_000);
            }
        }
    }

    private function touchSessionState(ExamAttempt $attempt): void
    {
        $tenantId = (string) tenant('id');
        $remaining = $attempt->getTimeRemainingSeconds();

        $this->stateStore->touch($tenantId, $attempt->id, $remaining);

        event(new ExamSessionStateUpdated(
            attemptId: $attempt->id,
            tenantId: $tenantId,
            timeRemainingSeconds: $remaining,
            connectionAlive: true,
        ));
    }
}
