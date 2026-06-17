<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Tenant;

use Exception;

class TenantAlreadySuspendedException extends Exception
{
    public function __construct(string $message = 'Tenant is already suspended.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
