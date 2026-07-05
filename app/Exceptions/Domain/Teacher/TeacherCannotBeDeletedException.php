<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Teacher;

use Exception;

class TeacherCannotBeDeletedException extends Exception
{
    public function __construct(string $message = 'Teacher cannot be deleted in their current state.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
