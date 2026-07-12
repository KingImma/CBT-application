<?php

declare(strict_types=1);

namespace App\Exceptions\Domain\Session;

use App\Exceptions\Domain\BaseDomainException;

class SessionDateRangeOverlapException extends BaseDomainException
{
    public function __construct(string $conflictingName, string $start, string $end, ?\Throwable $previous = null)
    {
        parent::__construct("The date range overlaps with an existing session ('{$conflictingName}' — {$start} to {$end}).", 422, $previous);
    }
}
