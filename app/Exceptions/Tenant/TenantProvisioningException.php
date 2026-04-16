<?php

declare(strict_types=1);

namespace App\Exceptions\Tenant;

use RuntimeException;

class TenantProvisioningException extends RuntimeException
{
    public function __construct(string $tenantSlug, string $reason)
    {
        parent::__construct(
            "Failed to provision tenant '{$tenantSlug}': {$reason}"
        );
    }
}