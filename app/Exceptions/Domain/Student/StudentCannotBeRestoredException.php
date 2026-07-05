<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Student;

use Exception;

class StudentCannotBeRestoredException extends Exception
{
    public function __construct(string $message = 'Student cannot be restored in their current state.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
