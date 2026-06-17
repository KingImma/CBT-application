<?php

declare(strict_types=1);

namespace App\Actions\Exceptions;

use Illuminate\Support\Facades\Log;

class MonitorException
{
    public function execute(string $exceptionClass, array $context): void
    {
        Log::channel('stack')->error("Monitored exception: {$exceptionClass}", $context);
    }
}
