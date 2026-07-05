<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Teacher;

use Exception;

class TeacherCannotBeRevokedException extends Exception
{
    public function __construct(string $message = 'Teacher cannot be revoked in their current state.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
