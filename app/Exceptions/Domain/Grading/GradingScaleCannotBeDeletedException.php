<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Grading;

use Exception;

class GradingScaleCannotBeDeletedException extends Exception
{
    public function __construct(string $message = 'Cannot delete the default grading scale.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
