<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Enums\ExamAttemptStatus;
use App\Events\ExamSessionStateUpdated;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use App\Support\Exam\ExamSessionStateStore;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RecordExamAnswer
{
    public function __construct(
        private ManageExamSession $sessionAction,
        private ExamSessionStateStore $stateStore,
    ) {}

    public function save(ExamAttempt $attempt, string $questionId, array $payload): ExamAnswer
    {
        if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
            throw new \RuntimeException('Attempt is no longer active.');
        }

        $attempt->load('exam');

        $answer = $this->retryOnUniqueViolation(function () use ($attempt, $questionId, $payload) {
            return DB::transaction(function () use ($attempt, $questionId, $payload) {
                $status = ExamAttempt::where('id', $attempt->id)->value('status');

                if ($status !== ExamAttemptStatus::InProgress->value) {
                    throw new \RuntimeException('Attempt is no longer active.');
                }

                if ($attempt->getTimeRemainingSeconds() <= 0) {
                    $this->sessionAction->finalizeExpiredAttempt($attempt);
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
        });

        $this->updateSessionState($attempt);

        return $answer;
    }

    public function bulkSave(ExamAttempt $attempt, array $answers): array
    {
        $saved = DB::transaction(function () use ($attempt, $answers) {
            if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
                throw new \RuntimeException('Attempt is no longer active.');
            }

            $results = [];

            foreach ($answers as $answerData) {
                $questionId = $answerData['question_id'];
                $questionSaves = [];

                $attempt->refresh();

                if ($attempt->status !== ExamAttemptStatus::InProgress->value) {
                    throw new \RuntimeException('Attempt is no longer active.');
                }

                if ($attempt->getTimeRemainingSeconds() <= 0) {
                    $this->sessionAction->finalizeExpiredAttempt($attempt);
                    throw new \RuntimeException('Exam time has expired.');
                }

                $questionSaves[] = $this->retryOnUniqueViolation(function () use ($attempt, $questionId, $answerData) {
                    return ExamAnswer::updateOrCreate(
                        [
                            'attempt_id' => $attempt->id,
                            'question_id' => $questionId,
                        ],
                        [
                            'selected_option_ids' => $answerData['selected_option_ids'] ?? null,
                            'text_answer' => $answerData['text_answer'] ?? null,
                            'answered_at' => now(),
                            'time_spent_seconds' => abs((int) now()->diffInSeconds($attempt->started_at, true)),
                        ]
                    );
                });

                $results[] = $questionSaves[0];
            }

            return $results;
        });

        $this->updateSessionState($attempt);

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

    private function retryOnUniqueViolation(callable $callback, int $maxAttempts = 3): mixed
    {
        $attempts = 0;

        while (true) {
            try {
                return $callback();
            } catch (QueryException $e) {
                if ($e->getCode() !== '23505' || ++$attempts >= $maxAttempts) {
                    throw $e;
                }

                usleep(50_000);
            }
        }
    }

    private function updateSessionState(ExamAttempt $attempt): void
    {
        $tenantId = (string) tenant('id');
        $remaining = $attempt->getTimeRemainingSeconds();
        $ttl = $attempt->exam->duration_minutes * 60 + 60;

        $this->stateStore->touch(
            tenantId: $tenantId,
            attemptId: $attempt->id,
            timeRemainingSeconds: $remaining,
        );

        event(new ExamSessionStateUpdated(
            attemptId: $attempt->id,
            tenantId: $tenantId,
            timeRemainingSeconds: $remaining,
            connectionAlive: true,
        ));
    }
}
