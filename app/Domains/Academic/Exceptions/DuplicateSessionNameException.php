<?php

declare(strict_types=1);

namespace App\Domains\Academic\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class DuplicateSessionNameException extends BaseDomainException
{
    public function __construct(string $name, ?\Throwable $previous = null)
    {
        parent::__construct("An academic session with the name '{$name}' already exists.", 422, $previous);
    }
}
