<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Exam;

use Exception;

class ExamCannotBeSubmittedException extends Exception
{
    public function __construct(string $message = 'Exam cannot be submitted for review in its current state.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
