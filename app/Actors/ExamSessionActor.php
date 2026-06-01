<?php

declare(strict_types=1);

namespace App\Actors;

use App\Actions\Tenants\Exam\ExamSessionAction;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant\ExamAnswer;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExamSessionActor
{
    private string $attemptId;

    private array $state;

    private const TTL = 600;

    public function __construct(string $attemptId)
    {
        $this->attemptId = $attemptId;
        $this->state = $this->load();
    }

    private function load(): array
    {
        $cached = Cache::get($this->cacheKey());
        if ($cached) {
            return $cached;
        }

        $attempt = ExamAttempt::with('exam')->findOrFail($this->attemptId);
        $exam = $attempt->exam;

        $sessionDeadline = null;
        if ($exam->session_started_at) {
            $sessionDeadline = $exam->session_started_at->getTimestamp() + ($exam->session_duration_minutes * 60);
        }

        $state = [
            'status' => $attempt->status,
            'started_at' => $attempt->started_at->getTimestamp(),
            'duration_seconds' => $exam->duration_minutes * 60,
            'session_deadline_ts' => $sessionDeadline,
            'answers' => ExamAnswer::where('attempt_id', $this->attemptId)
                ->get()
                ->keyBy('question_id')
                ->toArray(),
            'suspicious_events' => $attempt->suspicious_events ?? [],
            'max_suspicious' => $exam->settings->maxSuspiciousEvents,
        ];

        Cache::put($this->cacheKey(), $state, self::TTL);

        return $state;
    }

    public function handle(string $action, array $payload = []): mixed
    {
        return match ($action) {
            'saveAnswer' => $this->saveAnswer($payload),
            'timeRemaining' => $this->timeRemaining(),
            'logSuspicious' => $this->logSuspiciousEvent($payload),
            'submit' => $this->submit(),
            'getState' => $this->state,
            default => throw new \InvalidArgumentException("Unknown action: {$action}"),
        };
    }

    private function saveAnswer(array $payload): ExamAnswer
    {
        if ($this->state['status'] !== ExamAttemptStatus::InProgress->value) {
            throw new \RuntimeException('Attempt is no longer active.');
        }

        if ($this->timeRemaining() <= 0) {
            app(ExamSessionAction::class)->submit(
                ExamAttempt::findOrFail($this->attemptId)
            );
            throw new \RuntimeException('Exam time has expired.');
        }

        $this->state['answers'][$payload['question_id']] = [
            'selected_option_ids' => $payload['selected_option_ids'] ?? null,
            'time_spent_seconds' => $payload['time_spent_seconds'] ?? null,
            'text_answer' => $payload['text_answer'] ?? null,
            'answered_at' => now()->toIso8601String(),
        ];

        $this->flush();
        $answer = $this->persistAnswer($payload);

        return $answer;
    }

    private function timeRemaining(): int
    {
        $elapsed = time() - $this->state['started_at'];
        $attemptRemaining = $this->state['duration_seconds'] - $elapsed;

        if ($this->state['session_deadline_ts'] !== null) {
            $sessionRemaining = $this->state['session_deadline_ts'] - time();

            return max(0, min($attemptRemaining, $sessionRemaining));
        }

        return max(0, $attemptRemaining);
    }

    private function logSuspiciousEvent(array $payload): int
    {
        $this->state['suspicious_events'][] = [
            'type' => $payload['type'],
            'timestamp' => now()->toIso8601String(),
        ];

        $count = count($this->state['suspicious_events']);

        if ($count >= $this->state['max_suspicious']) {
            $this->state['status'] = ExamAttemptStatus::Disqualified->value;
        }

        $this->flush();
        $this->persistSuspiciousEvents();

        return $count;
    }

    private function submit(): void
    {
        $this->state['status'] = ExamAttemptStatus::Submitted->value;
        $this->flush();
        Cache::forget($this->cacheKey()); // Evict — actor lifecycle ends
    }

    // Flush in-memory state to Redis (fast path)
    private function flush(): void
    {
        Cache::put($this->cacheKey(), $this->state, self::TTL);
    }

    private function persistAnswer(array $payload): ExamAnswer
    {
        return DB::transaction(function () use ($payload) {
            return ExamAnswer::updateOrCreate(
                ['attempt_id' => $this->attemptId, 'question_id' => $payload['question_id']],
                [
                    'selected_option_ids' => $payload['selected_option_ids'] ?? null,
                    'time_spent_seconds' => $payload['time_spent_seconds'] ?? null,
                    'text_answer' => $payload['text_answer'] ?? null,
                    'answered_at' => now(),
                ]
            );
        });
    }

    private function persistSuspiciousEvents(): void
    {
        ExamAttempt::where('id', $this->attemptId)
            ->update(['suspicious_events' => $this->state['suspicious_events']]);
    }

    private function cacheKey(): string
    {
        return "exam_session_actor:{$this->attemptId}";
    }
}
