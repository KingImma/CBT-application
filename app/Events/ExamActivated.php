<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant\Exam;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExamActivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Exam $exam,
    ) {}
}
