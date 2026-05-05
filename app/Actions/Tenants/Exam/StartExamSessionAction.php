<?php

declare(strict_types=1);

namespace App\Actions\Tenants\Exam;

use App\Models\Tenant\Exam;
use Illuminate\Support\Facades\DB;

class StartExamSessionAction
{
    public function execute(Exam $exam): Exam
    {
        if ($exam->status !== 'scheduled') {
            throw new \RuntimeException('Only scheduled exams can start a session.');
        }

        return DB::transaction(function () use ($exam) {
            $exam->update([
                'status' => 'active',
                'session_started_at' => now(),
                'session_duration_minutes' => $exam->session_duration_minutes ?? ($exam->duration_minutes + 60),
            ]);

            return $exam->fresh();
        });
    }
}
