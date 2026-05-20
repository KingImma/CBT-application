<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Exam;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;

class ExamSessionEnded implements ShouldBroadcastNow
{
    use SerializesModels;

    public function __construct(public Exam $exam) {}

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
            'ended_at' => now()->toIso8601String(),
            'action' => 'force_submit',
        ];
    }

    public function broadcastAs(): string
    {
        return 'exam.session.ended';
    }
}
