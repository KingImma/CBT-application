<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Session;

use Exception;

class SessionAlreadyCurrentException extends Exception
{
    public function __construct(string $message = 'Academic session is already current.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
