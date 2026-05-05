<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\ExamAttempt;

class LogSuspiciousEventAction
{
    public function execute(ExamAttempt $attempt, string $type, array $metadata = []): void
    {
        $attempt->logSuspiciousEvent($type, $metadata);
    }
}
