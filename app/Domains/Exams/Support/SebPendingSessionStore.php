<?php

declare(strict_types=1);

namespace App\Domains\Exams\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

final class SebPendingSessionStore
{
    private function key(string $studentId): string
    {
        return "seb_pending_exam:{$studentId}";
    }

    public function write(string $studentId, string $examId): void
    {
        $ttlSeconds = (int) config('exams.seb.pending_session_ttl_minutes') * 60;
        $key = $this->key($studentId);

        Redis::hMSet($key, [
            'exam_id' => $examId,
            'created_at' => now()->toIso8601String(),
            'consumed_at' => '',
        ]);
        Redis::expire($key, $ttlSeconds);
    }

    /**
     * @return array{exam_id: string, created_at: string, consumed_at: ?string}|null
     */
    public function read(string $studentId): ?array
    {
        $data = Redis::hgetall($this->key($studentId));

        if (empty($data)) {
            return null;
        }

        return [
            'exam_id' => $data['exam_id'],
            'created_at' => $data['created_at'],
            'consumed_at' => $data['consumed_at'] !== '' ? $data['consumed_at'] : null,
        ];
    }

    public function markConsumed(string $studentId): void
    {
        Redis::hSet($this->key($studentId), 'consumed_at', now()->toIso8601String());
    }

    public function isWithinGraceWindow(string $consumedAt): bool
    {
        $graceSeconds = (int) config('exams.seb.grace_window_seconds');

        return now()->diffInSeconds(\Illuminate\Support\Carbon::parse($consumedAt)) <= $graceSeconds;
    }

    public function forget(string $studentId): void
    {
        try {
            Redis::del($this->key($studentId));
        } catch (\Throwable $e) {
            Log::warning('SebPendingSessionStore: forget failed', [
                'student_id' => $studentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
