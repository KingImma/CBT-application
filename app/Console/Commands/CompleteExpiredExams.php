<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Exams\Events\ExamAttemptsUpdated;
use App\Enums\ExamStatus;
use App\Models\Tenant;
use App\Models\Tenant\Exam;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompleteExpiredExams extends Command
{
    protected $signature = 'exams:complete-expired';

    protected $description = 'Mark active exams as completed when their window expires or all students have submitted.';

    public function handle(): int
    {
        $tenants = Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            try {
                $tenant->run(function () use ($tenant) {
                    $exams = Exam::query()
                        ->where('status', ExamStatus::Active)
                        ->where(function ($q) {
                            $q->where('window_end', '<', now())
                                ->orWhereColumn('completed_attempts', '>=', 'expected_attempts');
                        })
                        ->get();

                    foreach ($exams as $exam) {
                        $exam->update(['status' => ExamStatus::Completed]);

                        event(new ExamAttemptsUpdated(
                            examId: $exam->id,
                            completedAttempts: $exam->completed_attempts,
                            expectedAttempts: $exam->expected_attempts,
                            status: 'completed',
                            tenantId: (string) $tenant->id,
                        ));
                    }

                    if ($exams->isNotEmpty()) {
                        Log::info("Completed {$exams->count()} expired exam(s) for tenant {$tenant->id}.");
                    }
                });
            } catch (\Throwable $e) {
                // One unreachable/broken tenant DB must not sink the fleet run.
                Log::error('Expired-exam completion failed for tenant', [
                    'tenant_id' => (string) $tenant->id,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
