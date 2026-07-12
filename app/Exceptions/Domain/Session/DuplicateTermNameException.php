<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Session;

use App\Exceptions\Domain\BaseDomainException;

class DuplicateTermNameException extends BaseDomainException
{
    public function __construct(string $name, ?\Throwable $previous = null)
    {
        parent::__construct("A term with the name '{$name}' already exists in this session.", 422, $previous);
    }
}
