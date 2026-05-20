<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Enums\StatusType;
use App\Models\Tenant;
use Illuminate\Http\Exceptions\HttpResponseException;

class SuspendTenantAction
{
    /**
     * Suspend a tenant.
     *
     * @throws HttpResponseException if tenant is already suspended
     */
    public function handle(Tenant $tenant): void
    {
        $currentStatus = $tenant->subscription_status instanceof StatusType
            ? $tenant->subscription_status->value
            : $tenant->subscription_status;

        if ($currentStatus === StatusType::Suspended->value) {
            throw new HttpResponseException(
                response()->json(['message' => 'Tenant is already suspended'], 422)
            );
        }

        $tenant->update([
            'subscription_status' => StatusType::Suspended->value,
            'is_active' => false,
        ]);
    }
}
