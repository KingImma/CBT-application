<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\ExamAttempt;

class GetTimeRemainingAction
{
    public function execute(ExamAttempt $attempt): array
    {
        $remainingSeconds = $attempt->getTimeRemainingSeconds();
        
        return [
            'remaining_seconds' => $remainingSeconds,
            'expired' => $remainingSeconds <= 0,
        ];
    }
}
