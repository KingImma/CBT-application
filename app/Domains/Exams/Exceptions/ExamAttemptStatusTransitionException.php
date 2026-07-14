<?php

declare(strict_types=1);

namespace App\Domains\Exams\Exceptions;

use RuntimeException;

class ExamAttemptStatusTransitionException extends RuntimeException
{
    public function __construct(
        string $message = 'Invalid status transition',
        int $code = 409,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
