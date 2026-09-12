<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Exams\Actions\ForceCompleteExam;
use App\Enums\ExamStatus;
use App\Models\Tenant;
use App\Models\Tenant\Exam;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CompleteExpiredExams extends Command
{
    protected $signature = 'exams:complete-expired';
    protected $description = 'Force-completes standalone exams that have passed their deadline across all tenants';

    public function __construct(
        private ForceCompleteExam $forceCompleteExam
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        Tenant::where('is_active', true)->chunkById(100, function ($tenants) {
            foreach ($tenants as $tenant) {
                try {
                    $tenant->run(fn () => $this->completeExams((string) $tenant->id));
                } catch (\Throwable $e) {
                    Log::error('Expired exam completion failed for tenant', [
                        'tenant_id' => (string) $tenant->id,
                        'reason'    => $e->getMessage(),
                    ]);
                }
            }
        });

        return self::SUCCESS;
    }

    private function completeExams(string $tenantId): void
    {
        $exams = Exam::query()
            ->where('status', ExamStatus::Active)
            ->whereNotNull('window_end')
            ->where('window_end', '<=', now())
            ->cursor();

        foreach ($exams as $exam) {
            try {
                $this->forceCompleteExam->execute($exam);

                Log::info('Standalone exam auto-completed', [
                    'tenant_id' => $tenantId,
                    'exam_id'   => $exam->id,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Standalone exam auto-completion skipped', [
                    'tenant_id' => $tenantId,
                    'exam_id'   => $exam->id,
                    'reason'    => $e->getMessage(),
                ]);
            }
        }
    }
}
