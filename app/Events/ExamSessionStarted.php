<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Exam;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ExamSessionStarted implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public readonly Exam $exam) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('school.'.tenant('id').'.exam.'.$this->exam->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'exam_id' => $this->exam->id,
            'session_started_at' => $this->exam->session_started_at->toIso8601String(),
            'duration_minutes' => $this->exam->duration_minutes,
        ];
    }

    public function broadcastAs(): string
    {
        return 'exam.session.started';
    }
}
