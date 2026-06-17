<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Exam;

use Exception;

class ExamSessionStaleException extends Exception
{
    public function __construct(string $message = 'Exam session is stale', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
