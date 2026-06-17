<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Tenant;

use Exception;

class TenantAlreadyActiveException extends Exception
{
    public function __construct(string $message = 'Tenant is already active.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
