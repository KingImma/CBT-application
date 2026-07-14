<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Exceptions;

use App\Shared\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class TenantProvisioningException extends RuntimeException
{
    public function __construct(
        private readonly string $tenantSlug,
        private readonly string $reason
    ) {
        parent::__construct(
            "Failed to provision tenant '{$tenantSlug}': {$reason}"
        );
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error(
            "Failed to provision school '{$this->tenantSlug}': {$this->reason}",
            500
        );
    }
}
