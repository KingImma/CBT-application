<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\General;

use Exception;

class InvalidStateException extends Exception
{
    public function __construct(string $message = 'Invalid state.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
