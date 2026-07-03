<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Exam;

use Exception;

class ExamCannotBeDeletedException extends Exception
{
    public function __construct(string $message = 'Exam cannot be deleted in its current state.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
