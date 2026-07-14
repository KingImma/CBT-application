<?php

declare(strict_types=1);

namespace App\Domains\Exams\Events;

use App\Models\Tenant\ExamAttempt;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttemptGraded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ExamAttempt $attempt,
    ) {}
}
