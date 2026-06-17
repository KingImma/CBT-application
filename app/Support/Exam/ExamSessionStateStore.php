<?php

declare(strict_types=1);

namespace App\Support\Exam;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

final class ExamSessionStateStore
{
    private function key(string $tenantId, string $attemptId): string
    {
        return "exam:session:{$tenantId}:{$attemptId}";
    }

    private function logFailure(string $operation, string $attemptId, string $tenantId, \Throwable $e): void
    {
        Log::warning("ExamSessionStateStore: {$operation} failed", [
            'attempt_id' => $attemptId,
            'tenant_id' => $tenantId,
            'error' => $e->getMessage(),
        ]);
    }

    public function write(ExamSessionState $state, int $ttl): void
    {
        try {
            $key = $this->key($state->tenantId, $state->attemptId);
            Redis::hMSet($key, $state->toRedis());
            Redis::expire($key, $ttl);
        } catch (\Throwable $e) {
            $this->logFailure('write', $state->attemptId, $state->tenantId, $e);
        }
    }

    public function read(string $tenantId, string $attemptId): ?ExamSessionState
    {
        try {
            $data = Redis::hgetall($this->key($tenantId, $attemptId));

            if (empty($data)) {
                return null;
            }

            return ExamSessionState::fromRedis($attemptId, $tenantId, $data);
        } catch (\Throwable $e) {
            $this->logFailure('read', $attemptId, $tenantId, $e);

            return null;
        }
    }

    public function touch(string $tenantId, string $attemptId, int $timeRemainingSeconds, ?string $lastAnswerId = null): void
    {
        try {
            $key = $this->key($tenantId, $attemptId);
            Redis::hSet($key, 'time_remaining_seconds', (string) $timeRemainingSeconds);
            Redis::hSet($key, 'last_activity_at', now()->toIso8601String());

            if ($lastAnswerId !== null) {
                Redis::hSet($key, 'last_answer_id', $lastAnswerId);
            }
        } catch (\Throwable $e) {
            $this->logFailure('touch', $attemptId, $tenantId, $e);
        }
    }

    public function destroy(string $tenantId, string $attemptId): void
    {
        try {
            Redis::del($this->key($tenantId, $attemptId));
        } catch (\Throwable $e) {
            $this->logFailure('destroy', $attemptId, $tenantId, $e);
        }
    }
}
