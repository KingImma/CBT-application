<?php

declare(strict_types=1);

namespace App\Domains\Exams\Events;

use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamComment;
use App\Models\Tenant\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamCommentAdded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Exam $exam,
        public User $admin,
        public ExamComment $comment
    ) {}
}
