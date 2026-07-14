<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Exams\Actions\Attempts\FinalizeAttempt;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TickActiveExamSessions extends Command
{
    private const int STALE_HEARTBEAT_SECONDS = 180;

    protected $signature = 'exams:tick-active-sessions';

    protected $description = 'Mark exam attempts with stale heartbeats as timed out';

    public function __construct(
        private FinalizeAttempt $finalizeAction,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenants = Tenant::where('is_active', true)->get();

        if ($tenants->isEmpty()) {
            $this->warn('No active tenants found.');

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $tenantId = (string) $tenant->id;

            $tenant->run(function () use ($tenantId) {
                $cutoff = now()->subSeconds(self::STALE_HEARTBEAT_SECONDS);

                ExamAttempt::with('exam')
                    ->where('status', ExamAttemptStatus::InProgress->value)
                    ->where(function ($q) use ($cutoff) {
                        $q->where('last_heartbeat_at', '<', $cutoff)
                            ->orWhereNull('last_heartbeat_at');
                    })
                    ->chunk(100, function ($attempts) use ($tenantId) {
                        foreach ($attempts as $attempt) {
                            try {
                                $this->finalizeAction->execute(
                                    $attempt,
                                    reason: 'stale_heartbeat',
                                );
                                $this->line("  Timed out attempt {$attempt->id} for student {$attempt->student_id}");

                                Log::info('Exam attempt timed out by heartbeat tick', [
                                    'tenant_id' => $tenantId,
                                    'attempt_id' => $attempt->id,
                                    'student_id' => $attempt->student_id,
                                    'exam_id' => $attempt->exam_id,
                                ]);
                            } catch (\Exception $e) {
                                $this->error("  Failed to time out attempt {$attempt->id}: {$e->getMessage()}");
                            }
                        }
                    });
            });
        }

        return self::SUCCESS;
    }
}
