<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Events;

use App\Models\Tenant\Submission;
use App\Models\Tenant\SubmissionComment;
use App\Models\Tenant\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubmissionChangesRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Submission $submission,
        public User $admin,
        public SubmissionComment $comment,
    ) {}
}
