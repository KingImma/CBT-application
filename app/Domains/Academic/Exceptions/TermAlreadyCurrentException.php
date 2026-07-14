<?php

declare(strict_types=1);

namespace App\Domains\Academic\Exceptions;

use Exception;

class TermAlreadyCurrentException extends Exception
{
    public function __construct(string $message = 'Term is already current.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
