<?php

declare(strict_types=1);

namespace App\Support\Exam;

use App\Models\Tenant\ExamSessionState as ExamSessionStateModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

final class ExamSessionStateStore
{
    private function key(string $tenantId, string $attemptId): string
    {
        return "exam:session:{$tenantId}:{$attemptId}";
    }

    private function logFailure(
        string $operation,
        string $attemptId,
        string $tenantId,
        \Throwable $e,
    ): void {
        Log::critical(
            "ExamSessionStateStore: {$operation} failed — falling back to database",
            [
                "attempt_id" => $attemptId,
                "tenant_id" => $tenantId,
                "error" => $e->getMessage(),
            ],
        );
    }

    public function write(ExamSessionState $state, int $ttl): void
    {
        try {
            $key = $this->key($state->tenantId, $state->attemptId);
            Redis::hMSet($key, $state->toRedis());
            Redis::expire($key, $ttl);
        } catch (\Throwable $e) {
            $this->logFailure("write", $state->attemptId, $state->tenantId, $e);
            $this->writeToDatabase($state);
        }
    }

    public function read(string $tenantId, string $attemptId): ?ExamSessionState
    {
        try {
            $data = Redis::hgetall($this->key($tenantId, $attemptId));

            if (!empty($data)) {
                return ExamSessionState::fromRedis(
                    $attemptId,
                    $tenantId,
                    $data,
                );
            }
        } catch (\Throwable $e) {
            $this->logFailure("read", $attemptId, $tenantId, $e);
        }

        // Fallback: try database (either Redis returned empty or threw)
        return $this->readFromDatabase($tenantId, $attemptId);
    }

    public function touch(
        string $tenantId,
        string $attemptId,
        int $timeRemainingSeconds,
        ?string $lastAnswerId = null,
    ): void {
        try {
            $key = $this->key($tenantId, $attemptId);
            Redis::hSet(
                $key,
                "time_remaining_seconds",
                (string) $timeRemainingSeconds,
            );
            Redis::hSet($key, "last_activity_at", now()->toIso8601String());

            if ($lastAnswerId !== null) {
                Redis::hSet($key, "last_answer_id", $lastAnswerId);
            }
        } catch (\Throwable $e) {
            $this->logFailure("touch", $attemptId, $tenantId, $e);
            $this->touchDatabase($tenantId, $attemptId, $timeRemainingSeconds);
        }
    }

    public function destroy(string $tenantId, string $attemptId): void
    {
        try {
            Redis::del($this->key($tenantId, $attemptId));
        } catch (\Throwable $e) {
            $this->logFailure("destroy", $attemptId, $tenantId, $e);
        }

        // Always clean up database too (best-effort)
        try {
            ExamSessionStateModel::where("tenant_id", $tenantId)
                ->where("attempt_id", $attemptId)
                ->delete();
        } catch (\Throwable $e) {
            Log::warning("ExamSessionStateStore: database destroy failed", [
                "attempt_id" => $attemptId,
                "tenant_id" => $tenantId,
                "error" => $e->getMessage(),
            ]);
        }
    }

    private function writeToDatabase(ExamSessionState $state): void
    {
        try {
            ExamSessionStateModel::updateOrCreate(
                [
                    "tenant_id" => $state->tenantId,
                    "attempt_id" => $state->attemptId,
                ],
                [
                    "time_remaining_seconds" => $state->timeRemainingSeconds,
                    "connection_alive" => $state->connectionAlive,
                    "last_activity_at" => now(),
                ],
            );
        } catch (\Throwable $e) {
            Log::critical("ExamSessionStateStore: database write also failed", [
                "attempt_id" => $state->attemptId,
                "tenant_id" => $state->tenantId,
                "error" => $e->getMessage(),
            ]);
        }
    }

    private function readFromDatabase(
        string $tenantId,
        string $attemptId,
    ): ?ExamSessionState {
        try {
            $row = ExamSessionStateModel::where("tenant_id", $tenantId)
                ->where("attempt_id", $attemptId)
                ->first();

            if ($row === null) {
                return null;
            }

            return new ExamSessionState(
                attemptId: $row->attempt_id,
                tenantId: $row->tenant_id,
                timeRemainingSeconds: $row->time_remaining_seconds,
                connectionAlive: $row->connection_alive,
            );
        } catch (\Throwable $e) {
            Log::critical("ExamSessionStateStore: database read also failed", [
                "attempt_id" => $attemptId,
                "tenant_id" => $tenantId,
                "error" => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function touchDatabase(
        string $tenantId,
        string $attemptId,
        int $timeRemainingSeconds,
    ): void {
        try {
            ExamSessionStateModel::updateOrCreate(
                [
                    "tenant_id" => $tenantId,
                    "attempt_id" => $attemptId,
                ],
                [
                    "time_remaining_seconds" => $timeRemainingSeconds,
                    "connection_alive" => true,
                    "last_activity_at" => now(),
                ],
            );
        } catch (\Throwable $e) {
            Log::critical("ExamSessionStateStore: database touch also failed", [
                "attempt_id" => $attemptId,
                "tenant_id" => $tenantId,
                "error" => $e->getMessage(),
            ]);
        }
    }
}
