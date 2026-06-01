<?php

declare(strict_types=1);

namespace App\Exceptions\Business;

use Exception;

class PlanLimitExceededException extends Exception
{
    public function __construct(string $resource = 'resource')
    {
        parent::__construct("Plan limit exceeded for {$resource}.");
    }
}
