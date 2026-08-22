<?php

declare(strict_types=1);

namespace App\Domains\Assessments\Events;

use App\Models\Tenant\AssessmentSchedule;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssessmentActivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AssessmentSchedule $schedule,
    ) {}
}
