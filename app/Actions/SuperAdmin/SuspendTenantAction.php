<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Enums\StatusType;
use App\Models\Tenant;
use Illuminate\Http\Exceptions\HttpResponseException;

class SuspendTenantAction
{
    use Concerns\ResolvesTenantStatus;

    /**
     * Suspend a tenant.
     *
     * @throws HttpResponseException if tenant is already suspended
     */
    public function handle(Tenant $tenant): void
    {
        if ($this->resolveStatusValue($tenant) === StatusType::Suspended->value) {
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
