<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ExamAttemptsUpdated implements ShouldBroadcast
{
    public function __construct(
        public readonly string $examId,
        public readonly int $completedAttempts,
        public readonly int $expectedAttempts,
        public readonly string $status,
        public readonly string $tenantId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("school-admin.{$this->tenantId}.exam.{$this->examId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'exam.attempts.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'exam_id' => $this->examId,
            'completed_attempts' => $this->completedAttempts,
            'expected_attempts' => $this->expectedAttempts,
            'status' => $this->status,
        ];
    }
}
