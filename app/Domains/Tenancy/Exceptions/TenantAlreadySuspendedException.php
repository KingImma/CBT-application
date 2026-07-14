<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Exceptions;



class TenantAlreadySuspendedException extends BaseDomainException
{
    protected $message = 'Tenant is already suspended.';

    protected int $httpStatus = 409;
}
