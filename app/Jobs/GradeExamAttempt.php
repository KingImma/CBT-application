<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Tenants\Exam\ManageExamSession;
use App\Enums\ExamAttemptStatus;
use App\Models\Tenant;
use App\Models\Tenant\ExamAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GradeExamAttempt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $attemptId,
        public readonly string $tenantId,
    ) {}

    public function handle(ManageExamSession $sessionManager): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            Log::warning('GradeExamAttempt: tenant not found', [
                'tenant_id' => $this->tenantId,
                'attempt_id' => $this->attemptId,
            ]);

            return;
        }

        $tenant->run(function () use ($sessionManager) {
            DB::transaction(function () use ($sessionManager) {
                $attempt = ExamAttempt::where('id', $this->attemptId)
                    ->lockForUpdate()
                    ->first();

                if ($attempt === null) {
                    Log::warning('GradeExamAttempt: attempt not found', [
                        'attempt_id' => $this->attemptId,
                        'tenant_id' => $this->tenantId,
                    ]);

                    return;
                }

                if ($attempt->status !== ExamAttemptStatus::Submitted->value) {
                    return;
                }

                $sessionManager->gradeAttempt($attempt, $attempt->exam);
            });
        });
    }

    public function failed(\Throwable $e): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($e) {
            DB::transaction(function () use ($e) {
                $attempt = ExamAttempt::where('id', $this->attemptId)
                    ->lockForUpdate()
                    ->first();

                if ($attempt === null) {
                    return;
                }

                if ($attempt->status === ExamAttemptStatus::Grading->value) {
                    $attempt->status = ExamAttemptStatus::Failed->value;
                    $attempt->save();
                }

                Log::error('GradeExamAttempt failed', [
                    'attempt_id' => $this->attemptId,
                    'tenant_id' => $this->tenantId,
                    'error' => $e->getMessage(),
                ]);
            });
        });
    }
}
