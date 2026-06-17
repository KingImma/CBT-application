<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Tenant;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantSuspended
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
    ) {}
}
