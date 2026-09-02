<?php

declare(strict_types=1);

namespace App\Jobs;

class BeginTenantProvisioning
{
    public function handle($tenant): void
    {
        app()->instance('tenancy.provisioning', true);
    }
}
