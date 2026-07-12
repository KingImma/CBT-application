<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Tenant;

use App\Exceptions\Domain\BaseDomainException;

class TenantAlreadySuspendedException extends BaseDomainException
{
    protected $message = 'Tenant is already suspended.';

    protected int $httpStatus = 409;
}
