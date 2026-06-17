<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ExamSessionStateUpdated implements ShouldBroadcast
{
    public function __construct(
        public readonly string $attemptId,
        public readonly string $tenantId,
        public readonly int $timeRemainingSeconds,
        public readonly ?string $lastAnswerId = null,
        public readonly ?string $lastActivityAt = null,
        public readonly bool $connectionAlive = false,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("exam-session.{$this->attemptId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'exam.session.state';
    }

    public function broadcastWith(): array
    {
        return [
            'attempt_id' => $this->attemptId,
            'time_remaining_seconds' => $this->timeRemainingSeconds,
            'last_answer_id' => $this->lastAnswerId,
            'last_activity_at' => $this->lastActivityAt,
            'connection_alive' => $this->connectionAlive,
        ];
    }
}
