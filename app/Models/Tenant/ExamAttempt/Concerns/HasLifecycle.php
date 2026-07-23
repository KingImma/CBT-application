<?php

declare(strict_types=1);

namespace App\Models\Tenant\ExamAttempt\Concerns;

use App\Enums\SuspiciousEventType;

trait HasLifecycle
{
    public function logSuspiciousEvent(SuspiciousEventType $type, array $metadata = []): void
    {
        $events = $this->suspicious_events ?? [];
        $events[] = [
            'type' => $type->value,
            'timestamp' => now()->toIso8601String(),
            'metadata' => $metadata,
        ];
        $this->suspicious_events = $events;
    }
}
