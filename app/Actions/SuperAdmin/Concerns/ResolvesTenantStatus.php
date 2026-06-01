<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin\Concerns;

use App\Enums\StatusType;
use App\Models\Tenant;

trait ResolvesTenantStatus
{
    private function resolveStatusValue(Tenant $tenant): string
    {
        return $tenant->subscription_status instanceof StatusType
            ? $tenant->subscription_status->value
            : $tenant->subscription_status;
    }
}
