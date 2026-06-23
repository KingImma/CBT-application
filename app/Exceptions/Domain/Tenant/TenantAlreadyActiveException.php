<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Tenant;

use App\Exceptions\Domain\BaseDomainException;

class TenantAlreadyActiveException extends BaseDomainException
{
    protected $message = 'Tenant is already active.';

    protected int $httpStatus = 409;
}
