<?php

declare(strict_types=1);

namespace App\Domains\Tenancy\Exceptions;



class TenantAlreadyActiveException extends BaseDomainException
{
    protected $message = 'Tenant is already active.';

    protected int $httpStatus = 409;
}
