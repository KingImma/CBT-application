<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Models\Tenant;

class UpdateTenantAction
{
    /**
     * Update a tenant.
     *
     * @param  array<int,mixed>  $data
     */
    public function execute(array $data, Tenant $tenant): Tenant
    {
        $tenant->update($data);

        return $tenant;
    }
}
