<?php

namespace App\Actions\Tenants;

use App\Models\Tenant;

class UpdateTenantAction
{
    /**
     * Update a tenant.
     *
     * @param array<int,mixed> $data
     * @return Tenant
     */
    public function handle(array $data, Tenant $tenant): Tenant
    {
        $tenant->update($data);
        
        return $tenant;
    }
}
