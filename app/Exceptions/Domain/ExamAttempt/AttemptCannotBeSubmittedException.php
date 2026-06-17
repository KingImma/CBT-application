<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\ExamAttempt;

use Exception;

class AttemptCannotBeSubmittedException extends Exception
{
    public function __construct(string $message = 'Attempt cannot be submitted in its current state.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
