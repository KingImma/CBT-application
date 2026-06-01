<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Enums\StatusType;
use App\Models\Tenant;
use Illuminate\Http\Exceptions\HttpResponseException;

class ReinstateTenantAction
{
    use Concerns\ResolvesTenantStatus;

    /**
     * Reactivate a suspended tenant.
     *
     * @throws HttpResponseException if tenant is not currently suspended
     */
    public function handle(Tenant $tenant): void
    {
        if ($this->resolveStatusValue($tenant) !== StatusType::Suspended->value) {
            throw new HttpResponseException(
                response()->json(['message' => 'Tenant is not suspended'], 422)
            );
        }

        $tenant->update([
            'subscription_status' => StatusType::Active->value,
            'is_active' => true,
        ]);
    }
}
