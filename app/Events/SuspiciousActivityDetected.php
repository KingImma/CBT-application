<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\ExamAttempt;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class SuspiciousActivityDetected implements ShouldBroadcast
{
    public function __construct(
        public ExamAttempt $attempt,
        public string $eventType,
        public array $metadata = [],
    ) {}

    public function broadcastOn(): array
    {
        $exam = $this->attempt->exam;
        return [
            new PrivateChannel("teacher.{$exam->created_by}.exam.{$exam->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'attempt_id' => $this->attempt->id,
            'student_name' => $this->attempt->student->first_name . ' ' . $this->attempt->student->last_name,
            'event_type' => $this->eventType,
            'timestamp' => now()->toIso8601String(),
            'metadata' => $this->metadata,
        ];
    }
}
