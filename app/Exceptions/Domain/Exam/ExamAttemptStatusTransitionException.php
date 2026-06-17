<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Exam;

use Exception;

class ExamAttemptStatusTransitionException extends Exception
{
    public function __construct(string $message = 'Invalid status transition', int $code = 409, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
