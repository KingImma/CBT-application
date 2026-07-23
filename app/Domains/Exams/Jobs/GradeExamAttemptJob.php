<?php

declare(strict_types=1);

namespace App\Domains\Exams\Jobs;

use App\Domains\Exams\Actions\Attempts\GradeExamAttempt as GradeExamAttemptAction;
use App\Domains\Exams\State\ExamAttemptStateMachine;
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
use RuntimeException;

/**
 * Queue transport for grading. Owns exactly two responsibilities the
 * synchronous Action must not know about:
 *
 *   1. Claiming the row â€” Submitted â†’ Grading â€” under a row lock, so two
 *      queue workers racing on the same attempt can't both grade it.
 *   2. Recovering from a permanent failure â€” failed() only fires for a row
 *      genuinely stuck mid-grade, because step 1 is what makes "stuck
 *      in Grading" a real, reachable state instead of a state nothing
 *      ever writes.
 *
 * The scoring logic itself (QuestionGrader, Percentage, ResolveGrade)
 * stays entirely inside Actions\Tenants\Exam\GradeExamAttempt â€” this class
 * never touches a mark or a percentage.
 */
class GradeExamAttemptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly string $attemptId,
        public readonly string $tenantId,
    ) {
        $this->onQueue('exams');
    }

    public function handle(GradeExamAttemptAction $gradeAttempt, ExamAttemptStateMachine $stateMachine): void
    {
        Log::debug('GradeExamAttemptJob: handle started', [
            'attempt_id' => $this->attemptId,
            'tenant_id' => $this->tenantId,
        ]);

        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            Log::warning('GradeExamAttemptJob: tenant not found', [
                'tenant_id' => $this->tenantId,
                'attempt_id' => $this->attemptId,
            ]);

            return;
        }

        $tenant->run(function () use ($gradeAttempt, $stateMachine) {
            Log::debug('GradeExamAttemptJob: tenant context initialized', [
                'attempt_id' => $this->attemptId,
            ]);

            $claimed = DB::transaction(function () use ($stateMachine) {
                Log::debug('GradeExamAttemptJob: claim phase started', [
                    'attempt_id' => $this->attemptId,
                ]);

                $attempt = ExamAttempt::where('id', $this->attemptId)
                    ->lockForUpdate()
                    ->first();

                if ($attempt === null) {
                    Log::warning('GradeExamAttemptJob: attempt not found', [
                        'attempt_id' => $this->attemptId,
                    ]);

                    return null;
                }

                try {
                    $nextStatus = $stateMachine->claimForGrading($attempt);
                } catch (RuntimeException $e) {
                    Log::debug("GradeExamAttemptJob: skipping attempt {$this->attemptId} â€” unexpected status", [
                        'status' => $attempt->status,
                    ]);

                    return null;
                }

                if ($attempt->status !== ExamAttemptStatus::Grading->value) {
                    $attempt->status = $nextStatus->value;
                    $attempt->save();

                    Log::debug('GradeExamAttemptJob: claimed attempt for grading', [
                        'attempt_id' => $this->attemptId,
                        'new_status' => ExamAttemptStatus::Grading->value,
                    ]);
                }

                return $attempt;
            });

            if ($claimed === null) {
                return;
            }

            Log::debug('GradeExamAttemptJob: grading phase started', [
                'attempt_id' => $this->attemptId,
            ]);

            $gradeAttempt->execute($claimed->fresh());

            Log::debug('GradeExamAttemptJob: grading phase completed', [
                'attempt_id' => $this->attemptId,
            ]);
        });
    }

    /**
     * Fires only after all retries are exhausted. Because handle() now
     * actually writes Grading before scoring begins, a row can genuinely
     * be found sitting in Grading here â€” meaning the process died mid-score
     * (DB timeout, OOM, etc.) rather than never having started.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('GradeExamAttemptJob failed permanently', [
            'attempt_id' => $this->attemptId,
            'tenant_id' => $this->tenantId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            Log::error('GradeExamAttemptJob: cannot recover attempt â€” tenant not found in failed handler', [
                'tenant_id' => $this->tenantId,
                'attempt_id' => $this->attemptId,
            ]);

            return;
        }

        $tenant->run(function () {
            DB::transaction(function () {
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
            });
        });
    }
}
