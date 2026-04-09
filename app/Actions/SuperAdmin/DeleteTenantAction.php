<?php

declare (strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Models\Tenant;

class DeleteTenantAction
{
    /**
     * Delete a tenant.
     *
     * @param Tenant $tenant
     */
    public function handle(Tenant $tenant): void
    {
        $tenant->delete();
    }
}
