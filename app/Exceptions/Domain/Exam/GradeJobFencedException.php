<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Exam;

use Exception;

class GradeJobFencedException extends Exception
{
    public function __construct(string $message = 'Grade job is fenced by another worker', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
