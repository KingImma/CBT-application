<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Exam;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class ExamSessionEnded implements ShouldBroadcast
{
    public function __construct(
        public Exam $exam,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];
        // Broadcast to all students who have an in-progress attempt
        $studentIds = $exam->attempts()->inProgress()->pluck('student_id')->unique();
        
        foreach ($studentIds as $studentId) {
            $channels[] = new PrivateChannel("student.{$studentId}.exam.{$exam->id}");
        }
        
        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'exam_id' => $this->exam->id,
            'ended_at' => now()->toIso8601String(),
        ];
    }
}
