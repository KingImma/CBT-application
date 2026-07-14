<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use Exception;

class BulkOperationException extends Exception
{
    public function __construct(
        string $message = 'Bulk operation completed with errors.',
        private readonly array $results = [],
    ) {
        parent::__construct($message);
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
