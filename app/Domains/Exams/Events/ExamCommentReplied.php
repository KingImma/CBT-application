<?php

declare(strict_types=1);

namespace App\Domains\Exams\Events;

use App\Models\Tenant\Exam;
use App\Models\Tenant\ExamComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamCommentReplied
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Exam $exam,
        public ExamComment $parentComment,
        public ExamComment $reply
    ) {}
}
