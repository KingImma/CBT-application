<?php

declare(strict_types=1);

namespace App\Jobs;

class EndTenantProvisioning
{
    public function handle($tenant): void
    {
        app()->forgetInstance('tenancy.provisioning');
    }
}
