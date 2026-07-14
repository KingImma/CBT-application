<?php

declare(strict_types=1);

namespace App\Domains\Exams\Support;

final class ExamSessionState
{
    public function __construct(
        public readonly string $attemptId,
        public readonly string $tenantId,
        public readonly int $timeRemainingSeconds,
        public readonly ?string $lastAnswerId = null,
        public readonly ?string $lastActivityAt = null,
        public readonly bool $connectionAlive = false,
    ) {}

    public static function fromRedis(string $attemptId, string $tenantId, array $data): self
    {
        return new self(
            attemptId: $attemptId,
            tenantId: $tenantId,
            timeRemainingSeconds: (int) ($data['time_remaining_seconds'] ?? 0),
            lastAnswerId: $data['last_answer_id'] ?? null,
            lastActivityAt: $data['last_activity_at'] ?? null,
            connectionAlive: (bool) ($data['connection_alive'] ?? false),
        );
    }

    public function toRedis(): array
    {
        return [
            'time_remaining_seconds' => (string) $this->timeRemainingSeconds,
            'last_answer_id' => $this->lastAnswerId ?? '',
            'last_activity_at' => $this->lastActivityAt ?? '',
            'connection_alive' => $this->connectionAlive ? '1' : '0',
        ];
    }
}
