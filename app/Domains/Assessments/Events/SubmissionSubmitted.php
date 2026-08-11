<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Events;

use App\Models\Tenant\Submission;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubmissionSubmitted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Submission $submission,
    ) {}
}
