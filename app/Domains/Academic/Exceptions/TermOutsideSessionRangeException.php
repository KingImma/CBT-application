<?php

declare(strict_types=1);

namespace App\Domains\Academic\Exceptions;

use App\Domains\Tenancy\Exceptions\BaseDomainException;

class TermOutsideSessionRangeException extends BaseDomainException
{
    public function __construct(string $sessionName, string $sessionStart, string $sessionEnd, ?\Throwable $previous = null)
    {
        parent::__construct("The term dates must fall within the parent session '{$sessionName}' ({$sessionStart} to {$sessionEnd}).", 422, $previous);
    }
}
